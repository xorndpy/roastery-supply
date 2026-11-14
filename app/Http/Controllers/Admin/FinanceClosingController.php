<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceClosing;
use App\Services\FinanceClosingService;
use DomainException;
use Illuminate\Http\Request;

/** Tutup buku: kunci periode + snapshot laba rugi. Buka kembali hanya penutupan terakhir, dengan alasan. */
class FinanceClosingController extends Controller
{
    public function __construct(private FinanceClosingService $service) {}

    public function index()
    {
        $closings = FinanceClosing::with('closer:id,name')->orderByDesc('period_end')->get()
            ->map(fn ($c) => [$c, $this->service->drift($c)]);

        return view('admin.finance.closings', [
            'rows' => $closings,
            'suggestedStart' => $this->service->suggestedStart(),
            'lockedUntil' => $this->service->lockedUntil(),
            'yesterday' => now('Asia/Jakarta')->subDay()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->service->close($data['period_start'], $data['period_end'], $data['note'] ?? null, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['period_end' => $e->getMessage()]);
        }

        return back()->with('success', 'Periode ditutup. Biaya pada periode itu sekarang terkunci.');
    }

    public function reopen(Request $request, FinanceClosing $closing)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']], ['reason.required' => 'Alasan membuka kembali wajib diisi.', 'reason.min' => 'Alasan minimal 5 karakter.']);

        try {
            $this->service->reopen($closing, $data['reason'], $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Penutupan dibuka kembali.');
    }
}
