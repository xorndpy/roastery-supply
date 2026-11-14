<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use App\Services\FinanceClosingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Biaya operasional (CRUD). Izin per aksi dijaga route; periode yang sudah ditutup buku terkunci. */
class ExpenseController extends Controller
{
    public function __construct(private FinanceClosingService $closings) {}

    private function lockedError(string $date): ?string
    {
        if ($this->closings->isLocked($date)) {
            return 'Periode sampai '.Carbon::parse($this->closings->lockedUntil())->format('d M Y').' sudah ditutup buku. Buka kembali penutupannya di menu Tutup Buku bila perlu mengubah.';
        }

        return null;
    }

    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'category' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $filter = function ($q) use ($request) {
            if (array_key_exists((string) $request->query('category'), Expense::CATEGORIES)) {
                $q->where('category', $request->query('category'));
            }
            if ($request->filled('from')) {
                $q->where('expense_date', '>=', $request->query('from'));
            }
            if ($request->filled('to')) {
                $q->where('expense_date', '<=', $request->query('to'));
            }
            if ($search = trim((string) $request->query('search'))) {
                $q->where('description', 'like', '%'.addcslashes($search, '%_\\').'%');
            }
        };

        $total = (float) Expense::query()->tap($filter)->sum('amount');
        $expenses = Expense::query()->tap($filter)->with('creator:id,name')
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();
        $lockedUntil = $this->closings->lockedUntil();

        return view('admin.expenses.index', compact('expenses', 'total', 'lockedUntil'));
    }

    public function create()
    {
        return view('admin.expenses.form', ['expense' => null]);
    }

    public function store(ExpenseRequest $request)
    {
        $data = $request->validated();
        if ($err = $this->lockedError($data['expense_date'])) {
            return back()->withInput()->withErrors(['expense_date' => $err]);
        }

        $data['is_recurring'] = $request->boolean('is_recurring');
        $data['attachment'] = $request->hasFile('attachment') ? $request->file('attachment')->store('expenses', 'local') : null;

        Expense::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.expenses.index')->with('success', 'Biaya operasional dicatat.');
    }

    public function edit(Expense $expense)
    {
        return view('admin.expenses.form', compact('expense'));
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $data = $request->validated();
        $err = $this->lockedError($expense->expense_date->toDateString()) ?? $this->lockedError($data['expense_date']);
        if ($err) {
            return back()->withInput()->withErrors(['expense_date' => $err]);
        }

        $data['is_recurring'] = $request->boolean('is_recurring');
        unset($data['attachment']);
        if ($request->hasFile('attachment')) {
            if ($expense->attachment) {
                Storage::disk('local')->delete($expense->attachment);
            }
            $data['attachment'] = $request->file('attachment')->store('expenses', 'local');
        } elseif ($request->boolean('remove_attachment') && $expense->attachment) {
            Storage::disk('local')->delete($expense->attachment);
            $data['attachment'] = null;
        }

        $expense->update($data);

        return redirect()->route('admin.expenses.index')->with('success', 'Biaya operasional diperbarui.');
    }

    public function destroy(Expense $expense)
    {
        if ($err = $this->lockedError($expense->expense_date->toDateString())) {
            return redirect()->route('admin.expenses.index')->with('error', $err);
        }

        $expense->delete(); // soft delete; berkas lampiran tetap disimpan sebagai bukti

        return redirect()->route('admin.expenses.index')->with('success', 'Biaya operasional dihapus.');
    }

    /** Lampiran disimpan di disk privat dan hanya bisa dibuka lewat route ini (izin view_expenses). */
    public function attachment(Expense $expense)
    {
        abort_unless($expense->attachment && Storage::disk('local')->exists($expense->attachment), 404);

        return Storage::disk('local')->response($expense->attachment);
    }

    /** Salin biaya berulang bulan lalu ke bulan ini (tanpa duplikat, lewati periode terkunci). */
    public function copyRecurring(Request $request)
    {
        $now = now('Asia/Jakarta');
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $created = 0;

        foreach (Expense::where('is_recurring', true)->whereBetween('expense_date', [$prevStart->toDateString(), $prevEnd->toDateString()])->get() as $src) {
            $day = min($src->expense_date->day, $now->copy()->endOfMonth()->day);
            $date = $now->copy()->startOfMonth()->day($day)->toDateString();

            if ($date > $now->toDateString() || $this->closings->isLocked($date)) {
                continue;
            }
            $exists = Expense::whereBetween('expense_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
                ->where('category', $src->category)->where('description', $src->description)->exists();
            if ($exists) {
                continue;
            }

            Expense::create([
                'expense_date' => $date, 'category' => $src->category, 'description' => $src->description,
                'amount' => $src->amount, 'note' => $src->note, 'is_recurring' => true, 'created_by' => $request->user()->id,
            ]);
            $created++;
        }

        return back()->with($created ? 'success' : 'error', $created ? "{$created} biaya berulang disalin ke bulan ini." : 'Tidak ada biaya berulang baru untuk disalin (sudah ada, belum jatuh tempo, atau tidak ada bulan lalu).');
    }
}
