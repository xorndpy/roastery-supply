<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Statistik utama
        $totalRevenue = Order::whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
            ->sum('total');

        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'menunggu_bayar')->count();
        $totalProducts = Product::where('is_active', true)->count();
        $totalCustomers = Customer::count();
        $lowStockProducts = Product::whereColumn('stock', '<=', 'stock_threshold')
            ->where('is_active', true)
            ->count();
        $pendingPayments = Payment::where('status', 'pending')->count();

        // Order terbaru
        $recentOrders = Order::with(['customer', 'items'])
            ->latest()
            ->take(5)
            ->get();

        // Produk stok menipis
        $lowStockList = Product::whereColumn('stock', '<=', 'stock_threshold')
            ->where('is_active', true)
            ->orderBy('stock', 'asc')
            ->take(5)
            ->get();

        // Chart: penjualan 7 hari terakhir
        $salesChart = Order::whereIn('status', ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'])
            ->where('created_at', '>=', now()->subDays(7))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.dashboard', compact(
            'totalRevenue',
            'totalOrders',
            'pendingOrders',
            'totalProducts',
            'totalCustomers',
            'lowStockProducts',
            'pendingPayments',
            'recentOrders',
            'lowStockList',
            'salesChart'
        ));
    }
}