<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnOrder;
use App\Models\Setting;
use App\Support\GatewayFee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan laba rugi untuk satu periode (tanggal menurut WIB; database tetap UTC).
 *
 *   Penjualan neto     = subtotal pesanan terbayar - diskon voucher
 *   Pendapatan bruto   = penjualan neto - refund retur disetujui + jasa servis berbayar
 *   PPN keluaran       = bagian PPN di dalam pendapatan bruto (hanya bila PPN diaktifkan; harga dianggap sudah termasuk PPN)
 *   Pendapatan bersih  = pendapatan bruto - PPN
 *   Laba kotor         = pendapatan bersih - HPP (cost_price saat pesanan dibuat x qty)
 *   Laba bersih        = laba kotor - biaya operasional - biaya payment gateway
 *
 * Ongkir tidak dihitung sebagai pendapatan (diteruskan ke kurir). HPP yang belum diisi dihitung 0 dan dilaporkan
 * di missing_cost_* supaya pembaca tahu angkanya belum lengkap.
 */
class ProfitLossService
{
    /** Status pesanan yang sudah dibayar (sama dengan dashboard). */
    public const PAID = ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'];

    public function taxRate(): ?float
    {
        if ((string) Setting::get('tax_enabled') !== '1') {
            return null;
        }
        $rate = (float) Setting::get('tax_rate');

        return $rate > 0 ? $rate : (float) config('finance.default_tax_rate', 11);
    }

    public function report(string $from, string $to): array
    {
        $tz = 'Asia/Jakarta';
        $start = Carbon::createFromFormat('Y-m-d', $from, $tz)->startOfDay()->utc();
        $end = Carbon::createFromFormat('Y-m-d', $to, $tz)->endOfDay()->utc();
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $orders = fn () => Order::whereIn('status', self::PAID)->whereBetween('created_at', [$start, $end]);

        $orderCount = (int) $orders()->count();
        $grossSales = (float) $orders()->sum('subtotal');
        $discount = (float) $orders()->sum('discount');
        $shipping = (float) $orders()->sum('shipping_cost');
        $netSales = $grossSales - $discount;

        $refund = (float) ReturnOrder::where('status', 'approved')
            ->whereRaw('COALESCE(approved_at, updated_at) BETWEEN ? AND ?', [$startStr, $endStr])
            ->sum('refund_amount');

        // Jasa servis yang sudah dibayar pelanggan (garansi = Rp 0, tidak masuk).
        $serviceRevenue = (float) DB::table('service_tickets')
            ->whereNull('deleted_at')->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startStr, $endStr])->sum('final_cost');

        $grossRevenue = $netSales - $refund + $serviceRevenue;
        $rate = $this->taxRate();
        $ppn = $rate ? round($grossRevenue * $rate / (100 + $rate), 2) : 0.0;
        $revenue = $grossRevenue - $ppn;

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', self::PAID)
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_id, order_items.product_name, '
                .'SUM(order_items.quantity) as qty, '
                .'SUM(order_items.subtotal) as revenue, '
                .'SUM(COALESCE(order_items.cost_price, 0) * order_items.quantity) as cogs, '
                .'SUM(CASE WHEN order_items.cost_price IS NULL THEN order_items.quantity ELSE 0 END) as qty_no_cost')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($r) {
                $rev = (float) $r->revenue;
                $cogs = (float) $r->cogs;

                return [
                    'product_id' => $r->product_id,
                    'name' => $r->product_name,
                    'qty' => (int) $r->qty,
                    'revenue' => $rev,
                    'cogs' => $cogs,
                    'profit' => $rev - $cogs,
                    'margin' => $rev > 0 ? round(($rev - $cogs) / $rev * 100, 1) : null,
                    'qty_no_cost' => (int) $r->qty_no_cost,
                ];
            })
            ->all();

        $cogs = array_sum(array_column($rows, 'cogs'));
        $missingQty = array_sum(array_column($rows, 'qty_no_cost'));
        $missingProducts = count(array_filter($rows, fn ($r) => $r['qty_no_cost'] > 0));
        $grossProfit = $revenue - $cogs;

        $expensesByCategory = Expense::whereBetween('expense_date', [$from, $to])
            ->groupBy('category')
            ->selectRaw('category, SUM(amount) as total')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->map(fn ($v) => (float) $v)
            ->all();
        $expensesTotal = array_sum($expensesByCategory);

        // Biaya payment gateway: snapshot bila ada, selain itu dihitung dari aturan config/finance.php.
        $gateway = Payment::where('status', 'verified')->whereNotNull('provider')
            ->whereRaw('COALESCE(paid_at, verified_at, created_at) BETWEEN ? AND ?', [$startStr, $endStr])
            ->get(['id', 'payment_type', 'amount', 'gateway_fee']);
        $gatewayFees = (float) $gateway->sum(fn ($p) => $p->gateway_fee !== null ? (float) $p->gateway_fee : GatewayFee::for($p->payment_type, (float) $p->amount));

        $netProfit = $grossProfit - $expensesTotal - $gatewayFees;

        return [
            'from' => $from,
            'to' => $to,
            'order_count' => $orderCount,
            'gross_sales' => $grossSales,
            'discount' => $discount,
            'net_sales' => $netSales,
            'refund' => $refund,
            'service_revenue' => $serviceRevenue,
            'gross_revenue' => $grossRevenue,
            'tax_rate' => $rate,
            'ppn' => $ppn,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : null,
            'expenses_by_category' => $expensesByCategory,
            'expenses_total' => $expensesTotal,
            'gateway_fees' => $gatewayFees,
            'gateway_count' => $gateway->count(),
            'net_profit' => $netProfit,
            'net_margin' => $revenue > 0 ? round($netProfit / $revenue * 100, 1) : null,
            'shipping_collected' => $shipping,
            'products' => $rows,
            'missing_cost_qty' => (int) $missingQty,
            'missing_cost_products' => $missingProducts,
        ];
    }

    /** Tren bulanan (default 6 bulan terakhir sampai bulan dari $to). */
    public function trend(string $to, int $months = 6): array
    {
        $anchor = Carbon::createFromFormat('Y-m-d', $to, 'Asia/Jakarta')->startOfMonth();
        $today = now('Asia/Jakarta')->toDateString();
        $out = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $m = $anchor->copy()->subMonthsNoOverflow($i);
            $from = $m->toDateString();
            $end = min($m->copy()->endOfMonth()->toDateString(), $today);
            if ($from > $today) {
                continue;
            }
            $r = $this->report($from, $end);
            $out[] = [
                'label' => $m->locale('id')->translatedFormat('M y'),
                'from' => $from,
                'to' => $end,
                'revenue' => $r['revenue'],
                'gross_profit' => $r['gross_profit'],
                'net_profit' => $r['net_profit'],
            ];
        }

        return $out;
    }
}
