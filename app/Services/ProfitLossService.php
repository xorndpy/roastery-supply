<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\ReturnOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan laba rugi sederhana untuk satu periode (tanggal menurut WIB; database tetap UTC).
 *
 *   Penjualan neto   = subtotal pesanan terbayar - diskon voucher
 *   Pendapatan bersih = penjualan neto - refund retur yang disetujui
 *   Laba kotor       = pendapatan bersih - HPP (cost_price saat pesanan dibuat x qty)
 *   Laba bersih      = laba kotor - biaya operasional
 *
 * Ongkir tidak dihitung sebagai pendapatan (diteruskan ke kurir). HPP item yang belum diisi dihitung 0
 * dan dilaporkan di missing_cost_* supaya pembaca tahu angkanya belum lengkap.
 */
class ProfitLossService
{
    /** Status pesanan yang sudah dibayar (sama dengan dashboard). */
    public const PAID = ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'];

    public function report(string $from, string $to): array
    {
        $tz = 'Asia/Jakarta';
        $start = Carbon::createFromFormat('Y-m-d', $from, $tz)->startOfDay()->utc();
        $end = Carbon::createFromFormat('Y-m-d', $to, $tz)->endOfDay()->utc();

        $orders = fn () => Order::whereIn('status', self::PAID)->whereBetween('created_at', [$start, $end]);

        $orderCount = (int) $orders()->count();
        $grossSales = (float) $orders()->sum('subtotal');
        $discount = (float) $orders()->sum('discount');
        $shipping = (float) $orders()->sum('shipping_cost');
        $netSales = $grossSales - $discount;

        $refund = (float) ReturnOrder::where('status', 'approved')
            ->whereRaw('COALESCE(approved_at, updated_at) BETWEEN ? AND ?', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->sum('refund_amount');

        $revenue = $netSales - $refund;

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
                $revenue = (float) $r->revenue;
                $cogs = (float) $r->cogs;

                return [
                    'product_id' => $r->product_id,
                    'name' => $r->product_name,
                    'qty' => (int) $r->qty,
                    'revenue' => $revenue,
                    'cogs' => $cogs,
                    'profit' => $revenue - $cogs,
                    'margin' => $revenue > 0 ? round(($revenue - $cogs) / $revenue * 100, 1) : null,
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

        $netProfit = $grossProfit - $expensesTotal;

        return [
            'from' => $from,
            'to' => $to,
            'order_count' => $orderCount,
            'gross_sales' => $grossSales,
            'discount' => $discount,
            'net_sales' => $netSales,
            'refund' => $refund,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : null,
            'expenses_by_category' => $expensesByCategory,
            'expenses_total' => $expensesTotal,
            'net_profit' => $netProfit,
            'net_margin' => $revenue > 0 ? round($netProfit / $revenue * 100, 1) : null,
            'shipping_collected' => $shipping,
            'products' => $rows,
            'missing_cost_qty' => (int) $missingQty,
            'missing_cost_products' => $missingProducts,
        ];
    }
}
