<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\Testimonial;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Status yang dihitung sebagai pendapatan. */
    private const PAID = ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'];

    public function index(Request $request)
    {
        $range = in_array((int) $request->query('range'), [7, 30], true) ? (int) $request->query('range') : 7;
        $start = now()->subDays($range - 1)->startOfDay();
        $prevStart = $start->copy()->subDays($range);

        // ---- Total keseluruhan (tetap seperti sebelumnya) ----
        $totalRefund = (float) ReturnOrder::where('status', 'approved')->sum('refund_amount');
        $totalRevenue = Order::whereIn('status', self::PAID)->sum('total') - $totalRefund;

        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'menunggu_bayar')->count();
        $totalProducts = Product::where('is_active', true)->count();
        $totalCustomers = Customer::count();
        $lowStockProducts = Product::whereColumn('stock', '<=', 'stock_threshold')->where('is_active', true)->count();
        $pendingPayments = Payment::where('status', 'pending')->count();

        // Antrean kerja admin: pesanan yang sudah dibayar tapi belum diproses / belum dikirim (semua waktu).
        $ordersToProcess = Order::where('status', 'dikonfirmasi')->count();
        $ordersToShip = Order::where('status', 'diproses')->count();

        // ---- Periode terpilih vs periode sebelumnya ----
        $revenue = (float) Order::whereIn('status', self::PAID)->where('created_at', '>=', $start)->sum('total');
        $revenuePrev = (float) Order::whereIn('status', self::PAID)->whereBetween('created_at', [$prevStart, $start])->sum('total');
        $orders = Order::where('status', '!=', 'batal')->where('created_at', '>=', $start)->count();
        $ordersPrev = Order::where('status', '!=', 'batal')->whereBetween('created_at', [$prevStart, $start])->count();
        $revenueDelta = $revenuePrev > 0 ? (int) round(($revenue - $revenuePrev) / $revenuePrev * 100) : null;
        $ordersDelta = $ordersPrev > 0 ? (int) round(($orders - $ordersPrev) / $ordersPrev * 100) : null;

        // ---- Grafik pendapatan harian (tanggal kosong diisi 0) ----
        $daily = Order::whereIn('status', self::PAID)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, SUM(total) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $chartLabels = [];
        $chartDates = [];
        $chartData = [];
        foreach (CarbonPeriod::create($start, now()->startOfDay()) as $day) {
            $chartLabels[] = $day->copy()->locale('id')->translatedFormat('j M');
            $chartDates[] = $day->toDateString();
            $chartData[] = (float) ($daily[$day->toDateString()] ?? 0);
        }

        // ---- Distribusi status pesanan (donut) ----
        $statusLabels = [
            'menunggu_bayar' => 'Menunggu Bayar', 'dikonfirmasi' => 'Dikonfirmasi', 'diproses' => 'Diproses',
            'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal',
        ];
        $statusCounts = Order::where('created_at', '>=', $start)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $statusChart = [];
        foreach ($statusLabels as $key => $label) {
            $statusChart[] = ['key' => $key, 'label' => $label, 'count' => (int) ($statusCounts[$key] ?? 0)];
        }

        // ---- Produk terlaris pada periode ----
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', self::PAID)
            ->where('orders.created_at', '>=', $start)
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name as name, SUM(order_items.quantity) as qty, SUM(order_items.subtotal) as revenue')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        // ---- Daftar ----
        $recentOrders = Order::with(['customer', 'items'])->latest()->take(8)->get();
        $lowStockList = Product::whereColumn('stock', '<=', 'stock_threshold')
            ->where('is_active', true)
            ->orderBy('stock')
            ->take(5)
            ->get();

        // ---- Perlu tindakan ----
        $unreadMessages = ContactMessage::where('is_read', false)->count();
        $pendingTestimonials = Testimonial::where('is_approved', false)->count();
        $pendingReturns = ReturnOrder::where('status', 'pending')->count();
        $shippingConfigured = app(\App\Services\ShippingService::class)->configured();

        return view('admin.dashboard', compact(
            'range', 'revenue', 'revenueDelta', 'orders', 'ordersDelta',
            'chartLabels', 'chartDates', 'chartData', 'statusChart', 'topProducts', 'ordersToProcess', 'ordersToShip',
            'unreadMessages', 'pendingTestimonials', 'pendingReturns', 'shippingConfigured',
            'totalRefund', 'totalRevenue', 'totalOrders', 'pendingOrders', 'totalProducts',
            'totalCustomers', 'lowStockProducts', 'pendingPayments', 'recentOrders', 'lowStockList'
        ));
    }
}
