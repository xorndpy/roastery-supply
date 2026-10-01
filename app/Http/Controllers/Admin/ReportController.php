<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SalesExport;

class ReportController extends Controller
{
    /**
     * Dashboard laporan utama.
     */
    public function index(Request $request)
    {
        $period = $request->period ?? '30days';
        [$startDate, $endDate] = $this->getPeriodRange($period);

        // Statistik utama
        $stats = $this->getStats($startDate, $endDate);

        // Chart penjualan (harian)
        $salesChart = $this->getSalesChart($startDate, $endDate);

        // Produk terlaris (top 10)
        $topProducts = $this->getTopProducts($startDate, $endDate, 10);

        // Statistik per status
        $orderStatusStats = $this->getOrderStatusStats($startDate, $endDate);

        // Statistik pembayaran
        $paymentStats = $this->getPaymentStats($startDate, $endDate);

        return view('admin.reports.index', compact(
            'period',
            'startDate',
            'endDate',
            'stats',
            'salesChart',
            'topProducts',
            'orderStatusStats',
            'paymentStats'
        ));
    }

    /**
     * Laporan penjualan detail.
     */
    public function sales(Request $request)
    {
        $period = $request->period ?? '30days';
        [$startDate, $endDate] = $this->getPeriodRange($period);

        $query = Order::with(['customer', 'items'])
            ->whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Filter status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(20)->withQueryString();

        $total = $orders->sum('total');

        return view('admin.reports.sales', compact(
            'period',
            'startDate',
            'endDate',
            'orders',
            'total'
        ));
    }

    /**
     * Export Excel.
     */
    public function exportExcel(Request $request)
    {
        $period = $request->period ?? '30days';
        [$startDate, $endDate] = $this->getPeriodRange($period);

        $filename = 'laporan-penjualan-' . $startDate->format('Ymd') . '-' . $endDate->format('Ymd') . '.xlsx';

        return Excel::download(new SalesExport($startDate, $endDate), $filename);
    }

    /**
     * Export PDF.
     */
    public function exportPdf(Request $request)
    {
        $period = $request->period ?? '30days';
        [$startDate, $endDate] = $this->getPeriodRange($period);

        $orders = Order::with(['customer', 'items'])
            ->whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest()
            ->get();

        $stats = $this->getStats($startDate, $endDate);
        $topProducts = $this->getTopProducts($startDate, $endDate, 10);

        $pdf = Pdf::loadView('admin.reports.pdf', compact(
            'orders',
            'stats',
            'topProducts',
            'startDate',
            'endDate'
        ));

        $filename = 'laporan-penjualan-' . $startDate->format('Ymd') . '-' . $endDate->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Get range tanggal berdasarkan period.
     */
    protected function getPeriodRange(string $period): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7days' => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            '30days' => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * Statistik utama.
     */
    protected function getStats($startDate, $endDate): array
    {
        $orders = Order::whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRevenue = (clone $orders)->sum('total');
        $totalOrders = (clone $orders)->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $newCustomers = Customer::whereBetween('created_at', [$startDate, $endDate])->count();

        // Total produk terjual
        $totalProductsSold = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
              ->whereBetween('created_at', [$startDate, $endDate]);
        })->sum('quantity');

        // Pending orders
        $pendingOrders = Order::where('status', 'menunggu_bayar')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'avg_order_value' => $avgOrderValue,
            'new_customers' => $newCustomers,
            'total_products_sold' => $totalProductsSold,
            'pending_orders' => $pendingOrders,
        ];
    }

    /**
     * Chart data — penjualan harian.
     */
    protected function getSalesChart($startDate, $endDate)
    {
        $days = $startDate->diffInDays($endDate);
        $groupBy = $days <= 31 ? 'date' : 'month';

        $query = Order::whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($groupBy === 'date') {
            $data = $query->select(
                DB::raw('DATE(created_at) as label'),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        } else {
            $data = $query->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as label"),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        }

        return $data;
    }

    /**
     * Produk terlaris.
     */
    protected function getTopProducts($startDate, $endDate, int $limit = 10)
    {
        return OrderItem::select(
            'product_id',
            'product_name',
            DB::raw('SUM(quantity) as total_sold'),
            DB::raw('SUM(subtotal) as total_revenue')
        )
        ->whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
              ->whereBetween('created_at', [$startDate, $endDate]);
        })
        ->groupBy('product_id', 'product_name')
        ->orderByDesc('total_sold')
        ->limit($limit)
        ->get();
    }

    /**
     * Statistik per status order.
     */
    protected function getOrderStatusStats($startDate, $endDate): array
    {
        $statuses = ['menunggu_bayar', 'dikonfirmasi', 'diproses', 'dikirim', 'selesai', 'batal'];
        $stats = [];

        foreach ($statuses as $status) {
            $stats[$status] = Order::where('status', $status)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
        }

        return $stats;
    }

    /**
     * Statistik pembayaran.
     */
    protected function getPaymentStats($startDate, $endDate): array
    {
        $baseQuery = \App\Models\Payment::whereBetween('created_at', [$startDate, $endDate]);

        return [
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'verified' => (clone $baseQuery)->where('status', 'verified')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'total_verified' => (clone $baseQuery)->where('status', 'verified')->sum('amount'),
        ];
    }
}