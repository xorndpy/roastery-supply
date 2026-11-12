<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Expense;
use App\Services\ProfitLossService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Modul keuangan: laporan laba rugi dan HPP produk. */
class FinanceController extends Controller
{
    public function __construct(private ProfitLossService $service) {}

    /** Periode dari ?from=&to= (Y-m-d, WIB). Bawaan: awal bulan ini sampai hari ini. */
    private function period(Request $request): array
    {
        $tz = 'Asia/Jakarta';
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = now($tz)->startOfDay();
        $from = isset($data['from']) ? Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->startOfDay() : $today->copy()->startOfMonth();
        $to = isset($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to'], $tz)->startOfDay() : $today->copy();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 730) {
            $from = $to->copy()->subDays(730);
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    public function profit(Request $request)
    {
        [$from, $to] = $this->period($request);
        $report = $this->service->report($from, $to);

        return view('admin.finance.profit', compact('report', 'from', 'to'));
    }

    public function profitExport(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $r = $this->service->report($from, $to);

        // Cegah CSV injection dari teks bebas (nama produk) bila dibuka di Excel.
        $safe = fn (string $v) => $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v;
        $idr = fn (float $v) => (string) round($v);
        $pct = fn (?float $v) => $v === null ? '' : number_format($v, 1, ',', '');

        return response()->streamDownload(function () use ($r, $safe, $idr, $pct, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            $put = fn (array $row) => fputcsv($out, $row, ';');

            $put(['Laporan Laba Rugi', "{$from} s/d {$to}"]);
            $put([]);
            $put(['Keterangan', 'Jumlah (Rp)']);
            $put(['Penjualan produk (kotor)', $idr($r['gross_sales'])]);
            $put(['Diskon voucher', $idr(-$r['discount'])]);
            $put(['Penjualan neto', $idr($r['net_sales'])]);
            $put(['Retur / refund disetujui', $idr(-$r['refund'])]);
            $put(['Pendapatan bersih', $idr($r['revenue'])]);
            $put(['HPP', $idr(-$r['cogs'])]);
            $put(['Laba kotor', $idr($r['gross_profit']), 'Margin %', $pct($r['gross_margin'])]);
            foreach ($r['expenses_by_category'] as $cat => $total) {
                $put(['Biaya: '.(Expense::CATEGORIES[$cat] ?? $cat), $idr(-$total)]);
            }
            $put(['Total biaya operasional', $idr(-$r['expenses_total'])]);
            $put(['Laba bersih', $idr($r['net_profit']), 'Margin %', $pct($r['net_margin'])]);
            $put([]);
            $put(['Per produk']);
            $put(['Produk', 'Qty', 'Penjualan (Rp)', 'HPP (Rp)', 'Laba kotor (Rp)', 'Margin %', 'Qty tanpa HPP']);
            foreach ($r['products'] as $p) {
                $put([$safe((string) $p['name']), $p['qty'], $idr($p['revenue']), $idr($p['cogs']), $idr($p['profit']), $pct($p['margin']), $p['qty_no_cost']]);
            }
            fclose($out);
        }, "laba-rugi-{$from}-sd-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Daftar HPP per produk. */
    public function costs(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $filter = $request->query('filter') === 'kosong' ? 'kosong' : null;

        $products = Product::query()
            ->select(['id', 'name', 'sku', 'price', 'cost_price'])
            ->withSum(['orderItems as qty_no_cost' => fn ($q) => $q->whereNull('cost_price')], 'quantity')
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like));
            })
            ->when($filter === 'kosong', fn ($q) => $q->whereNull('cost_price'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $emptyCount = Product::whereNull('cost_price')->count();

        return view('admin.finance.costs', compact('products', 'search', 'filter', 'emptyCount'));
    }

    public function updateCosts(Request $request)
    {
        $data = $request->validate([
            'costs' => ['required', 'array', 'max:200'],
            'costs.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'apply_to_past' => ['nullable', 'boolean'],
        ], [
            'costs.*.numeric' => 'HPP harus berupa angka.',
            'costs.*.min' => 'HPP tidak boleh negatif.',
        ]);

        $applyToPast = (bool) ($data['apply_to_past'] ?? false);
        $changed = 0;

        $products = Product::whereIn('id', array_keys($data['costs']))->get();
        foreach ($products as $product) {
            $raw = $data['costs'][$product->id] ?? null;
            $new = $raw === null || $raw === '' ? null : round((float) $raw, 2);
            $old = $product->cost_price === null ? null : round((float) $product->cost_price, 2);

            if ($new !== $old) {
                $product->update(['cost_price' => $new]);
                $changed++;
            }

            if ($applyToPast && $new !== null) {
                OrderItem::where('product_id', $product->id)->whereNull('cost_price')->update(['cost_price' => $new]);
            }
        }

        return back()->with('success', $changed > 0 ? "HPP {$changed} produk diperbarui." : 'Tidak ada perubahan HPP.');
    }
}
