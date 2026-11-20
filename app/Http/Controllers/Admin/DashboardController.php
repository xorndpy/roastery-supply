<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\StockMovement;
use App\Models\Testimonial;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Status yang dihitung sebagai pendapatan. */
    private const PAID = ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'];

    public function index(Request $request)
    {
        // Admin Gudang punya dashboard sendiri: fokus kemas, kirim, dan stok (tanpa omzet/pembayaran).
        $u = $request->user();
        if ($u->hasRole('gudang') && !$u->hasAnyRole(['super_admin', 'admin', 'staff'])) {
            return $this->gudang();
        }

        $range = in_array((int) $request->query('range'), [7, 30, 90], true) ? (int) $request->query('range') : 7;
        // Hari dihitung menurut WIB (zona waktu aplikasi tetap UTC): "hari ini" di grafik = hari ini di Jakarta.
        $nowWib = now('Asia/Jakarta');
        $startWib = $nowWib->copy()->subDays($range - 1)->startOfDay();
        $start = $startWib->copy()->utc();
        $prevStart = $start->copy()->subDays($range);
        $todayStr = $nowWib->toDateString();

        // ---- Total keseluruhan (tetap seperti sebelumnya) ----
        $totalRefund = (float) ReturnOrder::where('status', 'approved')->sum('refund_amount');
        $totalRevenue = Order::whereIn('status', self::PAID)->sum('total') - $totalRefund;

        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'menunggu_bayar')->count();
        $totalProducts = Product::where('is_active', true)->count();
        $totalCustomers = Customer::count();
        $lowStockProducts = Product::whereColumn('stock', '<=', 'stock_threshold')->where('is_active', true)->count();
        // Perlu perhatian = bukti transfer manual menunggu dicek + pengecualian pembayaran gateway yang belum ditinjau.
        $pendingPayments = Payment::needsAttention()->count();
        $autoPaymentsWeek = Payment::gateway()->where('status', 'verified')->whereNull('verified_by')->where('verified_at', '>=', now()->subDays(7))->count();
        $recentGatewayPayments = Payment::gateway()->with('order')->whereIn('status', ['verified', 'review', 'duplicate', 'expired'])->latest('updated_at')->limit(5)->get();

        // Antrean kerja admin: pesanan yang sudah dibayar tapi belum diproses / belum dikirim (semua waktu).
        $ordersToProcess = Order::where('status', 'dikonfirmasi')->count();
        $ordersToShip = Order::where('status', 'diproses')->count();

        // Pesanan yang tertahan lebih dari 24 jam di tahapnya (updated_at = waktu terakhir status berubah).
        $lateCutoff = now()->subDay();
        $lateToProcess = Order::where('status', 'dikonfirmasi')->where('updated_at', '<=', $lateCutoff)->count();
        $lateToShip = Order::where('status', 'diproses')->where('updated_at', '<=', $lateCutoff)->count();

        // Follow-up: pelanggan yang pernah belanja tapi tidak ada pembelian dalam 90 hari terakhir.
        $followUps = collect();
        $followUpTotal = 0;
        if ($request->user()->can('view_customers')) {
            $paid = fn ($q) => $q->whereIn('status', self::PAID);
            $lapsed = Customer::query()
                ->whereHas('orders', $paid)
                ->whereDoesntHave('orders', fn ($q) => $q->whereIn('status', self::PAID)->where('created_at', '>=', now()->subDays(90)));

            $followUpTotal = (clone $lapsed)->count();
            $followUps = $lapsed
                ->withMax(['orders as last_paid_at' => $paid], 'created_at')
                ->withSum(['orders as lifetime_total' => $paid], 'total')
                ->orderByDesc('last_paid_at')
                ->limit(5)
                ->get();
        }

        // ---- Periode terpilih vs periode sebelumnya ----
        $revenue = (float) Order::whereIn('status', self::PAID)->where('created_at', '>=', $start)->sum('total');
        $revenuePrev = (float) Order::whereIn('status', self::PAID)->whereBetween('created_at', [$prevStart, $start])->sum('total');
        $orders = Order::where('status', '!=', 'batal')->where('created_at', '>=', $start)->count();
        $ordersPrev = Order::where('status', '!=', 'batal')->whereBetween('created_at', [$prevStart, $start])->count();
        $revenueDelta = $revenuePrev > 0 ? (int) round(($revenue - $revenuePrev) / $revenuePrev * 100) : null;
        $ordersDelta = $ordersPrev > 0 ? (int) round(($orders - $ordersPrev) / $ordersPrev * 100) : null;

        // ---- Grafik harian (pendapatan & jumlah pesanan; tanggal kosong diisi 0) ----
        $dailyRev = [];
        $dailyOrd = [];
        foreach (Order::where('created_at', '>=', $start)->get(['status', 'total', 'created_at']) as $o) {
            $d = $o->created_at->copy()->timezone('Asia/Jakarta')->toDateString();
            if (in_array($o->status, self::PAID, true)) {
                $dailyRev[$d] = ($dailyRev[$d] ?? 0) + (float) $o->total;
            }
            if ($o->status !== 'batal') {
                $dailyOrd[$d] = ($dailyOrd[$d] ?? 0) + 1;
            }
        }

        $chartLabels = [];
        $chartDates = [];
        $chartData = [];
        $chartOrders = [];
        foreach (CarbonPeriod::create($startWib, $nowWib->copy()->startOfDay()) as $day) {
            $key = $day->toDateString();
            $chartLabels[] = $day->copy()->locale('id')->translatedFormat('j M');
            $chartDates[] = $key;
            $chartData[] = (float) ($dailyRev[$key] ?? 0);
            $chartOrders[] = (int) ($dailyOrd[$key] ?? 0);
        }

        // ---- Rata-rata nilai pesanan & pelanggan pada periode ----
        $paidOrders = Order::whereIn('status', self::PAID)->where('created_at', '>=', $start)->count();
        $paidOrdersPrev = Order::whereIn('status', self::PAID)->whereBetween('created_at', [$prevStart, $start])->count();
        $aov = $paidOrders > 0 ? $revenue / $paidOrders : 0;
        $aovPrev = $paidOrdersPrev > 0 ? $revenuePrev / $paidOrdersPrev : 0;
        $aovDelta = $aovPrev > 0 ? (int) round(($aov - $aovPrev) / $aovPrev * 100) : null;

        $buyers = (int) Order::whereIn('status', self::PAID)->whereNotNull('customer_id')->where('created_at', '>=', $start)->distinct()->count('customer_id');
        $firstPaid = DB::table('orders')->whereNull('deleted_at')->whereIn('status', self::PAID)->whereNotNull('customer_id')
            ->groupBy('customer_id')->selectRaw('customer_id, MIN(created_at) as first_at');
        $newBuyers = (int) DB::query()->fromSub($firstPaid, 'f')->where('first_at', '>=', $start)->count();
        $newBuyers = min($newBuyers, $buyers);
        $repeatPct = $buyers > 0 ? (int) round(($buyers - $newBuyers) / $buyers * 100) : 0;

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

        // Foto produk (pencocokan lewat nama, sama seperti pengelompokan di atas).
        $imgByName = Product::with('images')->whereIn('name', $topProducts->pluck('name'))->get()->keyBy('name');
        $topProducts->transform(function ($row) use ($imgByName) {
            $row->image = $imgByName->get($row->name)?->images->first()?->image;

            return $row;
        });

        // ---- Penjualan per kategori (kategori induk) pada periode ----
        $categorySales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->whereNull('order_items.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.status', self::PAID)
            ->where('orders.created_at', '>=', $start)
            ->selectRaw('COALESCE(p.name, c.name) as name, SUM(order_items.quantity) as qty, SUM(order_items.subtotal) as revenue')
            ->groupByRaw('COALESCE(p.name, c.name)')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        // ---- Daftar ----
        $recentOrders = Order::with(['customer', 'items'])->latest()->take(8)->get();
        $lowStockList = Product::with('images')->whereColumn('stock', '<=', 'stock_threshold')
            ->where('is_active', true)
            ->orderBy('stock')
            ->take(5)
            ->get();

        // ---- Perlu tindakan ----
        $unreadMessages = ContactMessage::where('is_read', false)->count();
        $pendingTestimonials = Testimonial::where('is_approved', false)->count();
        $pendingReturns = ReturnOrder::where('status', 'pending')->count();
        $shippingConfigured = app(\App\Services\ShippingService::class)->configured();

        // ---- Laba bersih, konversi, target, dan sumber penjualan ----
        $profitNet = $profitDelta = null;
        if ($u->can('view_profit')) {
            $svc = app(\App\Services\ProfitLossService::class);
            $profitNet = (float) $svc->report($fromStr, $toStr)['net_profit'];
            $profitPrev = (float) $svc->report($startWib->copy()->subDays($days)->toDateString(), $startWib->copy()->subDay()->toDateString())['net_profit'];
            $profitDelta = $profitPrev != 0.0 ? (int) round(($profitNet - $profitPrev) / abs($profitPrev) * 100) : null;
        }

        $createdOrders = Order::where('created_at', '>=', $start)->where('created_at', '<=', $end)->count();
        $conversion = $createdOrders > 0 ? round($paidOrders / $createdOrders * 100, 1) : null;
        $createdPrev = Order::where('created_at', '>=', $prevStart)->where('created_at', '<', $start)->count();
        $conversionPrev = $createdPrev > 0 ? $paidOrdersPrev / $createdPrev * 100 : null;
        $conversionDelta = ($conversion !== null && $conversionPrev !== null) ? (int) round($conversion - $conversionPrev) : null;

        $salesTarget = (float) \App\Models\Setting::get('sales_target_monthly', 0);
        $monthStart = $nowWib->copy()->startOfMonth()->utc();
        $monthRevenue = (float) Order::whereIn('status', self::PAID)->where('created_at', '>=', $monthStart)->sum('total');
        $targetPct = $salesTarget > 0 ? (int) round($monthRevenue / $salesTarget * 100) : null;
        $daysInMonth = $nowWib->daysInMonth;
        $targetPace = $salesTarget > 0 ? (int) round($nowWib->day / $daysInMonth * 100) : null;

        $sourceRows = Payment::where('status', 'verified')->where('created_at', '>=', $start)->where('created_at', '<=', $end)
            ->get(['provider', 'payment_type', 'method', 'amount']);
        $sourceTotal = (float) $sourceRows->sum('amount');
        $salesSources = $sourceRows->groupBy(fn ($pay) => $pay->provider ? 'Midtrans' : 'Transfer manual')
            ->map(fn ($g, $label) => ['label' => $label, 'count' => $g->count(), 'amount' => (float) $g->sum('amount'),
                'pct' => $sourceTotal > 0 ? (int) round($g->sum('amount') / $sourceTotal * 100) : 0])
            ->sortByDesc('amount')->values();

        return view('admin.dashboard', compact(
            'range', 'revenue', 'revenueDelta', 'orders', 'ordersDelta',
            'chartLabels', 'chartDates', 'chartData', 'chartOrders', 'todayStr', 'paidOrders', 'aov', 'aovDelta', 'buyers', 'newBuyers', 'repeatPct', 'categorySales', 'statusChart', 'topProducts', 'ordersToProcess', 'ordersToShip', 'lateToProcess', 'lateToShip', 'followUps', 'followUpTotal', 'autoPaymentsWeek', 'recentGatewayPayments',
            'profitNet', 'profitDelta', 'createdOrders', 'conversion', 'conversionDelta', 'salesTarget', 'monthRevenue', 'targetPct', 'targetPace', 'salesSources', 'sourceTotal',
            'unreadMessages', 'pendingTestimonials', 'pendingReturns', 'shippingConfigured',
            'totalRefund', 'totalRevenue', 'totalOrders', 'pendingOrders', 'totalProducts',
            'totalCustomers', 'lowStockProducts', 'pendingPayments', 'recentOrders', 'lowStockList'
        ));
    }

<<<<<<< HEAD
=======
    /** Simpan target omzet bulanan (dipakai kartu "Target penjualan"). */
    public function setTarget(Request $request)
    {
        $data = $request->validate(['target' => 'required|numeric|min:0|max:100000000000']);
        \App\Models\Setting::set('sales_target_monthly', (string) (int) $data['target']);

        return back()->with('success', 'Target penjualan bulanan disimpan.');
    }

    /** Tentukan periode dari preset atau from/to. Hasil: [awal WIB, akhir WIB, kode preset|'custom']. */
    private function resolvePeriod(Request $request, Carbon $nowWib): array
    {
        $today = $nowWib->copy()->startOfDay();
        $preset = (string) $request->query('range', '');

        $range = match ($preset) {
            'today' => [$today->copy(), $today->copy()],
            '7', '30', '90' => [$today->copy()->subDays((int) $preset - 1), $today->copy()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()],
            'lastmonth' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            default => null,
        };

        if ($range === null) {
            $isDate = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4));
            $from = $request->query('from');
            $to = $request->query('to');

            if ($isDate($from) && $isDate($to)) {
                $a = Carbon::createFromFormat('Y-m-d', $from, 'Asia/Jakarta')->startOfDay();
                $b = Carbon::createFromFormat('Y-m-d', $to, 'Asia/Jakarta')->startOfDay();
                if ($a->gt($b)) {
                    [$a, $b] = [$b, $a];
                }
                $b = $b->gt($today) ? $today->copy() : $b;
                $a = $a->gt($b) ? $b->copy() : $a;
                $a = $a->lt($b->copy()->subDays(365)) ? $b->copy()->subDays(365) : $a; // maksimal 366 hari
                $range = [$a, $b];
                $preset = 'custom';
            } else {
                $range = [$today->copy()->subDays(29), $today->copy()];
                $preset = '30';
            }
        }

        return [$range[0]->startOfDay(), $range[1]->copy()->endOfDay(), $preset];
    }

    /** "9 Nov 2026" atau "10 Okt – 9 Nov 2026". */
    private function periodText(Carbon $a, Carbon $b): string
    {
        $a = $a->copy()->locale('id');
        $b = $b->copy()->locale('id');
        if ($a->isSameDay($b)) {
            return $b->translatedFormat('j M Y');
        }

        return $a->year === $b->year
            ? $a->translatedFormat('j M') . ' – ' . $b->translatedFormat('j M Y')
            : $a->translatedFormat('j M Y') . ' – ' . $b->translatedFormat('j M Y');
    }

>>>>>>> 7d57e25 (update modul dasboard)
    /** Data kalender pesanan: pesanan satu bulan, dikelompokkan per tanggal (WIB). */
    public function calendar(Request $request)
    {
        $data = $request->validate(['month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $month = $data['month'] ?? now('Asia/Jakarta')->format('Y-m');

        $from = Carbon::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00', 'Asia/Jakarta');
        $to = $from->copy()->endOfMonth();
        $finance = $request->user()->can('view_finance');

        $days = [];
        $orders = Order::with('customer:id,name')
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->orderBy('created_at')
            ->limit(600)
            ->get();

        foreach ($orders as $o) {
            $local = $o->created_at->copy()->timezone('Asia/Jakarta');
            $row = [
                'n' => $o->order_number,
                'c' => $o->customer->name ?? 'Guest',
                's' => $o->status,
                'l' => $o->status_label,
                'h' => $local->format('H:i'),
                'u' => route('admin.orders.show', $o->id),
            ];
            if ($finance) {
                $row['t'] = (float) $o->total;
            }
            $days[$local->toDateString()][] = $row;
        }

        return response()->json(['month' => $month, 'days' => $days]);
    }

    /** Dashboard khusus Admin Gudang. */
    private function gudang()
    {
        $toPack = Order::where('status', 'dikonfirmasi')->count();
        $toShip = Order::where('status', 'diproses')->count();
        $inTransit = Order::where('status', 'dikirim')->count();
        $returnsOpen = ReturnOrder::whereIn('status', ['pending', 'approved'])->count();

        $lowStock = Product::where('is_active', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'stock_threshold')->count();
        $outOfStock = Product::where('is_active', true)->where('stock', '<=', 0)->count();

        // Antrean kerja: yang paling lama menunggu tampil duluan.
        $queue = Order::with('items')->whereIn('status', ['dikonfirmasi', 'diproses'])->oldest()->take(8)->get();
        $lowStockList = Product::where('is_active', true)->whereColumn('stock', '<=', 'stock_threshold')->orderBy('stock')->take(8)->get();
        $movements = StockMovement::with('product')->latest()->take(8)->get();

        return view('admin.dashboard-gudang', compact(
            'toPack', 'toShip', 'inTransit', 'returnsOpen', 'lowStock', 'outOfStock', 'queue', 'lowStockList', 'movements'
        ));
    }
}
