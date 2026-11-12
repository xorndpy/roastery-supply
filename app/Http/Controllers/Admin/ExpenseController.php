<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\Request;

/** Biaya operasional (CRUD). Izin per aksi dijaga route: view/create/edit/delete_expenses. */
class ExpenseController extends Controller
{
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

        return view('admin.expenses.index', compact('expenses', 'total'));
    }

    public function create()
    {
        return view('admin.expenses.form', ['expense' => null]);
    }

    public function store(ExpenseRequest $request)
    {
        Expense::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.expenses.index')->with('success', 'Biaya operasional dicatat.');
    }

    public function edit(Expense $expense)
    {
        return view('admin.expenses.form', compact('expense'));
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $expense->update($request->validated());

        return redirect()->route('admin.expenses.index')->with('success', 'Biaya operasional diperbarui.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('admin.expenses.index')->with('success', 'Biaya operasional dihapus.');
    }
}
