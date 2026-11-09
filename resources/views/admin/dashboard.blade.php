@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $user = auth()->user();
    // Semua nominal uang (omzet, refund, grafik, total) hanya untuk yang punya view_finance.
    $canFinance = $user->can('view_finance');
    $statusColors = ['menunggu_bayar' => '#d97706', 'dikonfirmasi' => '#2563eb', 'diproses' => '#7c3aed', 'dikirim' => '#0891b2', 'selesai' => '#16a34a', 'batal' => '#dc2626'];
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');

    // Waktu tampil mengikuti WIB (zona waktu aplikasi tetap UTC agar data lama tidak bergeser).
    $now = now('Asia/Jakarta');
    $hour = (int) $now->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $firstName = \Illuminate\Support\Str::before($user->name ?? 'Admin', ' ');
    $dateLabel = $now->copy()->locale('id')->translatedFormat('l, j F Y');

    // Antrean kerja: hanya yang jumlahnya > 0 dan boleh diakses user ini.
    $ico = [
        'card' => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M3 10h18M7 15h3"/>',
        'box' => '<path d="M3.5 7.5l8.5-4 8.5 4v9l-8.5 4-8.5-4v-9z"/><path d="M3.5 7.5L12 11.5l8.5-4M12 11.5v9"/>',
        'truck' => '<path d="M3 6.5h11v9H3z"/><path d="M14 9.5h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'undo' => '<path d="M4 12a8 8 0 1 0 2.6-5.9"/><path d="M4 4.5v4h4"/>',
        'archive' => '<rect x="3.5" y="4" width="17" height="4.5" rx="1"/><path d="M5 8.5v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-10M10 12.5h4"/>',
        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M4 7.5l8 6 8-6"/>',
        'coins' => '<circle cx="12" cy="12" r="8.5"/><path d="M14.6 9.3c-.5-.9-1.4-1.3-2.6-1.3-1.4 0-2.4.7-2.4 1.8 0 2.4 5 1.2 5 3.6 0 1.1-1.1 1.9-2.6 1.9-1.2 0-2.2-.4-2.7-1.4M12 6.5V8M12 16v1.5"/>',
        'bag' => '<path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
        'users' => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3 19c.6-3.3 3-5 6-5s5.4 1.7 6 5"/><path d="M16 5.6a3.2 3.2 0 0 1 0 5.8M18 14.4c1.7.6 2.7 2.1 3 4.6"/>',
        'image' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5"/>',
        'star' => '<path d="M12 3.8l2.5 5.1 5.6.8-4.1 4 1 5.6-5-2.7-5 2.7 1-5.6-4.1-4 5.6-.8L12 3.8z"/>',
    ];
    $tasks = collect([
        ['count' => $pendingPayments, 'label' => 'Pembayaran perlu perhatian', 'hint' => 'Bukti transfer atau pengecualian gateway', 'href' => route('admin.payments.index', ['tab' => 'perhatian']), 'can' => 'verify_payments', 'icon' => 'card'],
        ['count' => $ordersToProcess, 'label' => 'Pesanan siap diproses', 'hint' => 'Sudah dibayar, belum dikemas' . ($lateToProcess > 0 ? ' · ' . $lateToProcess . ' lewat 24 jam' : ''), 'href' => route('admin.orders.index', ['status' => 'dikonfirmasi']), 'can' => 'view_orders', 'icon' => 'box'],
        ['count' => $ordersToShip, 'label' => 'Pesanan siap dikirim', 'hint' => 'Sudah dikemas, belum dikirim' . ($lateToShip > 0 ? ' · ' . $lateToShip . ' lewat 24 jam' : ''), 'href' => route('admin.orders.index', ['status' => 'diproses']), 'can' => 'view_orders', 'icon' => 'truck'],
        ['count' => $pendingReturns, 'label' => 'Retur menunggu keputusan', 'hint' => 'Perlu disetujui atau ditolak', 'href' => route('admin.returns.index'), 'can' => 'view_returns', 'icon' => 'undo'],
        ['count' => $lowStockProducts, 'label' => 'Produk stok menipis', 'hint' => 'Di bawah batas minimum', 'href' => route('admin.stock.index'), 'can' => 'view_stock', 'icon' => 'archive'],
        ['count' => $unreadMessages, 'label' => 'Pesan belum dibaca', 'hint' => 'Dari formulir kontak', 'href' => route('admin.messages.index', ['status' => 'unread']), 'can' => 'view_contact_messages', 'icon' => 'mail'],
        ['count' => $pendingTestimonials, 'label' => 'Testimoni menunggu', 'hint' => 'Perlu disetujui sebelum tampil', 'href' => route('admin.testimonials.index', ['status' => 'pending']), 'can' => 'view_testimonials', 'icon' => 'star'],
    ])->filter(fn ($t) => $t['count'] > 0 && $user->can($t['can']))->values();
    $taskTotal = (int) $tasks->sum('count');
    $lede = $taskTotal === 0
        ? 'Semua beres. Tidak ada yang menunggu tindakan Anda.'
        : 'Ada ' . $taskTotal . ' hal menunggu tindakan Anda, dimulai dari ' . \Illuminate\Support\Str::lower($tasks->first()['label']) . '.';

    $statusTotal = (int) collect($statusChart)->sum('count');
    $maxQty = max(1, (int) ($topProducts->first()->qty ?? 1));
@endphp

<div class="dash">

    {{-- ============ Header ============ --}}
    <header class="dash-head">
        <div>
            <h1 class="dash-h1">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="dash-lede">{{ $dateLabel }} · {{ $lede }}</p>
        </div>
        <nav class="dash-range" aria-label="Rentang waktu">
            @foreach([7, 30, 90] as $r)
                <a href="{{ route('admin.dashboard', ['range' => $r]) }}" @if($range === $r) aria-current="true" @endif>{{ $r }} hari</a>
            @endforeach
        </nav>
    </header>

    @if(!($shippingConfigured ?? true))
        @can('view_shipping_rates')
            <div class="dash-notice" role="note">
                <svg class="h-5 w-5 shrink-0 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.8l9 15.7H3L12 3.8z"/><path d="M12 10v4M12 17v.1"/></svg>
                <p><strong>Ongkir belum diatur.</strong> Checkout saat ini berjalan tanpa ongkos kirim. <a href="{{ route('admin.shipping.index') }}">Atur tarif pengiriman →</a></p>
            </div>
        @endcan
    @endif

    {{-- ============ KPI ============ --}}
    <section class="dash-kpis" aria-label="Ringkasan">
        @if($canFinance)
            <div class="dash-kpi">
                <div class="dash-kpi-top"><span class="dash-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico['coins'] !!}</svg></span><h2>Pendapatan {{ $range }} hari</h2></div>
                <p class="dash-kpi-num">{{ $rp($revenue) }}</p>
                <p class="dash-kpi-sub">
                    @if($revenueDelta === null)
                        Belum ada periode pembanding
                    @else
                        <span class="dash-delta {{ $revenueDelta >= 0 ? 'is-up' : 'is-down' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $revenueDelta >= 0 ? '' : 'rotate-180' }}"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
                            {{ abs($revenueDelta) }}%<span class="sr-only">{{ $revenueDelta >= 0 ? ' naik' : ' turun' }}</span>
                        </span>
                        dibanding periode lalu
                    @endif
                </p>
            </div>
        @else
            <div class="dash-kpi">
                <div class="dash-kpi-top"><span class="dash-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico['box'] !!}</svg></span><h2>Perlu tindakan</h2></div>
                <p class="dash-kpi-num">{{ number_format($taskTotal) }}</p>
                <p class="dash-kpi-sub">{{ $taskTotal > 0 ? 'hal menunggu Anda' : 'Semua beres' }}</p>
            </div>
        @endif

        <div class="dash-kpi">
            <div class="dash-kpi-top"><span class="dash-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico['bag'] !!}</svg></span><h2>Pesanan {{ $range }} hari</h2></div>
            <p class="dash-kpi-num">{{ number_format($orders) }}</p>
            <p class="dash-kpi-sub">{{ $pendingOrders > 0 ? $pendingOrders . ' belum dibayar' : 'Semua sudah dibayar' }}</p>
        </div>

        <div class="dash-kpi">
            <div class="dash-kpi-top"><span class="dash-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico['box'] !!}</svg></span><h2>Produk aktif</h2></div>
            <p class="dash-kpi-num">{{ number_format($totalProducts) }}</p>
            <p class="dash-kpi-sub">{{ $lowStockProducts > 0 ? $lowStockProducts . ' stok menipis' : 'Stok aman' }}</p>
        </div>

        <div class="dash-kpi">
            <div class="dash-kpi-top"><span class="dash-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico['users'] !!}</svg></span><h2>Pelanggan</h2></div>
            <p class="dash-kpi-num">{{ number_format($totalCustomers) }}</p>
            <p class="dash-kpi-sub">Terdaftar</p>
        </div>
    </section>

    {{-- ============ Utama: grafik + pesanan | antrean + status ============ --}}
    <div class="dash-main">

        <div class="dash-col">
            @if($canFinance)
            <section class="dash-card" aria-labelledby="rev-title">
                <div class="dash-card-head">
                    <div>
                        <h2 id="rev-title">Pendapatan harian</h2>
                        <p class="dash-sub">{{ $range }} hari terakhir · Total sejak toko dibuka {{ $rp($totalRevenue) }}{{ $totalRefund > 0 ? ' (setelah refund ' . $rp($totalRefund) . ')' : '' }}</p>
                    </div>
                </div>
                <div id="dashChart" class="dash-chart" tabindex="0" role="group" data-range="{{ $range }}"
                     aria-label="Grafik pendapatan harian {{ $range }} hari terakhir. Gunakan panah kiri dan kanan untuk menelusuri tiap hari."
                     data-dates='@json($chartDates)' data-values='@json($chartData)'></div>
                <p id="dashChartLive" class="sr-only" aria-live="polite"></p>
                <table class="sr-only">
                    <caption>Pendapatan harian</caption>
                    <thead><tr><th>Tanggal</th><th>Pendapatan</th></tr></thead>
                    <tbody>@foreach($chartDates as $i => $d)<tr><td>{{ $d }}</td><td>{{ $rp($chartData[$i]) }}</td></tr>@endforeach</tbody>
                </table>
            </section>
            @endif

            <section class="dash-card" aria-labelledby="orders-title">
                <div class="dash-card-head">
                    <h2 id="orders-title">Pesanan terbaru</h2>
                    <a href="{{ route('admin.orders.index') }}" class="dash-link">Lihat semua →</a>
                </div>
                <div class="dash-tablewrap overflow-x-auto">
                    <table class="dash-table">
                        <thead>
                            <tr><th>No. pesanan</th><th>Pelanggan</th>@if($canFinance)<th class="text-right">Total</th>@endif<th>Status</th><th class="text-right">Waktu</th></tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                <tr data-href="{{ route('admin.orders.show', $order->id) }}">
                                    <td><a href="{{ route('admin.orders.show', $order->id) }}" class="dash-order-no">{{ $order->order_number }}</a></td>
                                    <td class="is-name" title="{{ $order->customer->name ?? 'Guest' }}">{{ $order->customer->name ?? 'Guest' }}</td>
                                    @if($canFinance)<td class="text-right font-medium tabular-nums">{{ $rp($order->total) }}</td>@endif
                                    <td><span class="dash-badge" style="--c: {{ $statusColors[$order->status] ?? '#71717a' }}"><i></i>{{ $order->status_label }}</span></td>
                                    <td class="text-right text-xs text-stone-500" title="{{ $order->created_at->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i') }} WIB">{{ $order->created_at->locale('id')->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $canFinance ? 5 : 4 }}" class="py-12 text-center text-stone-500">Belum ada pesanan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="dash-col dash-side">
            <section class="dash-card" aria-labelledby="queue-title">
                <div class="dash-card-head">
                    <h2 id="queue-title">Perlu tindakan</h2>
                    @if($taskTotal > 0)<span class="dash-pill" aria-label="{{ $taskTotal }} hal">{{ $taskTotal }}</span>@endif
                </div>

                @if($tasks->isNotEmpty())
                    <ul class="dash-tasks">
                        @foreach($tasks as $t)
                            <li>
                                <a href="{{ $t['href'] }}" class="dash-task">
                                    <span class="dash-task-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico[$t['icon']] !!}</svg></span>
                                    <span class="dash-task-text"><b>{{ $t['label'] }}</b><small>{{ $t['hint'] }}</small></span>
                                    <span class="dash-task-count">{{ $t['count'] }}</span>
                                    <svg class="dash-task-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="dash-clear">
                        <span class="dash-clear-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                        <p class="dash-clear-title">Semua beres</p>
                        <p class="dash-clear-text">Tidak ada pembayaran, pesanan, atau pesan yang menunggu Anda saat ini.</p>
                    </div>
                @endif
            </section>

            <section class="dash-card" x-data="{ hov: null }" aria-labelledby="status-title">
                <div class="dash-card-head">
                    <div>
                        <h2 id="status-title">Status pesanan</h2>
                        <p class="dash-sub">{{ $range }} hari terakhir</p>
                    </div>
                    <p class="dash-big" aria-label="{{ $statusTotal }} pesanan">{{ $statusTotal }}</p>
                </div>

                @if($statusTotal > 0)
                    <div class="dash-segbar" role="img" aria-label="Distribusi status pesanan">
                        @foreach($statusChart as $s)
                            @if($s['count'] > 0)
                                <a href="{{ route('admin.orders.index', ['status' => $s['key']]) }}" class="dash-seg" tabindex="-1" aria-hidden="true"
                                   style="flex: {{ $s['count'] }} 1 0%; --c: {{ $statusColors[$s['key']] }}"
                                   :class="hov && hov !== '{{ $s['key'] }}' ? 'is-dim' : ''"
                                   @mouseenter="hov = '{{ $s['key'] }}'" @mouseleave="hov = null"></a>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="dash-segbar is-empty" role="img" aria-label="Belum ada pesanan"></div>
                @endif

                <ul class="dash-legend">
                    @foreach($statusChart as $s)
                        <li>
                            <a href="{{ route('admin.orders.index', ['status' => $s['key']]) }}"
                               :class="hov && hov !== '{{ $s['key'] }}' ? 'is-dim' : ''"
                               @mouseenter="hov = '{{ $s['key'] }}'" @mouseleave="hov = null" @focus="hov = '{{ $s['key'] }}'" @blur="hov = null">
                                <i style="background: {{ $statusColors[$s['key']] }}"></i>
                                <span>{{ $s['label'] }}</span>
                                <b>{{ $s['count'] }}</b>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>

    {{-- ============ Baris bawah: produk (berfoto), pembayaran online, follow-up ============ --}}
    <div class="dash-trio">

        <section class="dash-card" aria-labelledby="top-title">
            <div class="dash-card-head"><h2 id="top-title">Produk terlaris</h2><span class="dash-sub">{{ $range }} hari</span></div>
            @forelse($topProducts as $i => $p)
                @if($loop->first)<ol class="dash-prods">@endif
                <li>
                    @if(!empty($p->image))
                        <img src="{{ thumb_url($p->image) }}" alt="" loading="lazy" class="dash-thumb" onerror="this.onerror=null;this.src='{{ media_url($p->image) }}'">
                    @else
                        <span class="dash-thumb is-empty" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $ico['image'] !!}</svg></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3"><span class="truncate text-sm font-medium">{{ $p->name }}</span><span class="shrink-0 text-xs text-stone-600 tabular-nums">{{ (int) $p->qty }} terjual</span></div>
                        <div class="dash-track"><div class="dash-fill" style="width: {{ $p->qty / $maxQty * 100 }}%"></div></div>
                        @if($canFinance)<p class="mt-1 text-xs text-stone-500 tabular-nums">{{ $rp($p->revenue) }}</p>@endif
                    </div>
                </li>
                @if($loop->last)</ol>@endif
            @empty
                <p class="dash-empty">Belum ada penjualan dalam {{ $range }} hari terakhir.</p>
            @endforelse
        </section>

        <section class="dash-card" aria-labelledby="stock-title">
            <div class="dash-card-head">
                <h2 id="stock-title">Stok menipis</h2>
                @can('view_stock')<a href="{{ route('admin.stock.index') }}" class="dash-link">Kelola →</a>@endcan
            </div>
            @forelse($lowStockList as $product)
                @php $pimg = $product->images->first()->image ?? null; @endphp
                @if($loop->first)<ul class="dash-stock">@endif
                <li>
                    @if($pimg)
                        <img src="{{ thumb_url($pimg) }}" alt="" loading="lazy" class="dash-thumb" onerror="this.onerror=null;this.src='{{ media_url($pimg) }}'">
                    @else
                        <span class="dash-thumb is-empty" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $ico['image'] !!}</svg></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $product->name }}</p>
                        <p class="text-xs text-stone-500">{{ $product->sku }}</p>
                        <div class="dash-track is-brick"><div class="dash-fill" style="width: {{ min(100, $product->stock / max(1, $product->stock_threshold) * 100) }}%"></div></div>
                    </div>
                    <div class="shrink-0 text-right"><p class="dash-stock-n">{{ $product->stock }}</p><p class="text-[11px] text-stone-500">min {{ $product->stock_threshold }}</p></div>
                </li>
                @if($loop->last)</ul>@endif
            @empty
                <p class="dash-empty is-ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Semua stok di atas batas minimum.</p>
            @endforelse
        </section>

        @can('verify_payments')
        <section class="dash-card" aria-labelledby="auto-title">
            <div class="dash-card-head">
                <div>
                    <h2 id="auto-title">Pembayaran online</h2>
                    <p class="dash-sub">{{ $autoPaymentsWeek }} dikonfirmasi otomatis (7 hari)</p>
                </div>
                <a href="{{ route('admin.payments.index', ['tab' => 'otomatis']) }}" class="dash-link">Lihat →</a>
            </div>
            @forelse($recentGatewayPayments as $gp)
                @if($loop->first)<ul class="dash-stock">@endif
                <li>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.payments.show', $gp->id) }}" class="dash-order-no block truncate text-[13px]">{{ $gp->order->order_number }}</a>
                        <p class="text-xs text-stone-500">{{ $gp->methodLabel() }} · {{ $gp->updated_at->locale('id')->diffForHumans() }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        @if($canFinance)<p class="text-sm font-medium tabular-nums">{{ $rp($gp->amount) }}</p>@endif
                        <span class="dash-badge" style="--c: {{ ['verified' => '#16a34a', 'review' => '#dc2626', 'duplicate' => '#dc2626', 'expired' => '#71717a'][$gp->status] ?? '#71717a' }}"><i></i>{{ $gp->status === 'verified' ? 'Otomatis' : $gp->status }}</span>
                    </div>
                </li>
                @if($loop->last)</ul>@endif
            @empty
                <p class="dash-empty">Belum ada pembayaran online.</p>
            @endforelse
        </section>
        @endcan

        @can('view_customers')
        <section class="dash-card" aria-labelledby="fu-title">
            <div class="dash-card-head">
                <h2 id="fu-title">Follow-up pelanggan</h2>
                <a href="{{ route('admin.customers.index', ['segment' => 'dormant']) }}" class="dash-link">{{ $followUpTotal }} dormant →</a>
            </div>
            @forelse($followUps as $c)
                @php
                    $wa = preg_replace('/\D+/', '', (string) $c->phone);
                    if (str_starts_with($wa, '0')) { $wa = '62' . substr($wa, 1); } elseif (str_starts_with($wa, '8')) { $wa = '62' . $wa; }
                    $msg = 'Halo ' . $c->name . ', kami dari ' . \App\Models\Setting::get('site_name', config('app.name')) . '. Sudah cukup lama tidak mampir. Ada kebutuhan alat atau sparepart kopi? Kami siap bantu.';
                @endphp
                @if($loop->first)<ul class="dash-stock">@endif
                <li>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $c->name }}</p>
                        <p class="text-xs text-stone-500">Terakhir belanja {{ \Illuminate\Support\Carbon::parse($c->last_paid_at)->locale('id')->diffForHumans() }}@if($canFinance) · total {{ $rp($c->lifetime_total) }}@endif</p>
                    </div>
                    @if(strlen($wa) >= 9)
                        <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener"
                           class="shrink-0 rounded-full border border-green-300 bg-green-50 px-3 py-1 text-xs font-medium text-green-800 hover:bg-green-100">WhatsApp</a>
                    @endif
                </li>
                @if($loop->last)</ul>@endif
            @empty
                <p class="dash-empty is-ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Semua pelanggan berbelanja dalam waktu dekat.</p>
            @endforelse
        </section>
        @endcan
    </div>
</div>

@push('scripts')
<script>
(function () {
    const intro = document.documentElement.classList.contains('dash-intro');
    const nf = new Intl.NumberFormat('id-ID');
    const rp = v => 'Rp ' + nf.format(Math.round(v));
    const easeOut = t => 1 - Math.pow(1 - t, 4);

    /* ---------- angka naik (hanya saat animasi pembuka) ---------- */
    if (intro) {
        document.querySelectorAll('[data-count]').forEach(el => {
            const to = Number(el.dataset.count); if (!(to > 0)) return;
            const fmt = el.dataset.fmt === 'rp' ? rp : v => nf.format(Math.round(v));
            const dur = Number(el.dataset.dur || 800), delay = Number(el.dataset.delay || 0);
            el.textContent = fmt(0);
            setTimeout(() => { const t0 = performance.now(); (function tick(now) { const p = Math.min(1, (now - t0) / dur); el.textContent = fmt(to * easeOut(p)); if (p < 1) requestAnimationFrame(tick); })(t0); }, delay);
        });
    }

    /* ---------- baris tabel bisa diklik penuh ---------- */
    document.querySelectorAll('.dash-table tr[data-href]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('a')) window.location.href = tr.dataset.href; }));

    /* ---------- grafik pendapatan (SVG buatan sendiri) ---------- */
    const host = document.getElementById('dashChart'); if (!host) { try { sessionStorage.setItem('dashIntro', '1'); } catch (e) {} return; }
    const live = document.getElementById('dashChartLive');
    const dates = JSON.parse(host.dataset.dates), vals = JSON.parse(host.dataset.values).map(Number);
    const N = vals.length, total = vals.reduce((a, b) => a + b, 0), max = Math.max(0, ...vals), best = vals.indexOf(max), avg = total / Math.max(1, N);
    const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const dShort = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
    const dLong = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });
    const num1 = x => String(Number(x.toFixed(1))).replace('.', ',');
    const short = v => v >= 1e6 ? num1(v / 1e6) + ' jt' : v >= 1e3 ? num1(v / 1e3) + ' rb' : String(v);
    const ticks = m => { if (m <= 0) return [0, 1]; const raw = m / 3, mag = Math.pow(10, Math.floor(Math.log10(raw))); const step = [1, 2, 2.5, 5, 10].map(k => k * mag).find(s => s >= raw); const n = Math.ceil(m / step - 1e-9); return Array.from({ length: n + 1 }, (_, i) => i * step); };

    // kurva monoton: tidak pernah "melambung" di bawah nol atau di atas data
    function curve(p) {
        const n = p.length; if (n === 1) return `M${p[0].x},${p[0].y}`;
        const dx = [], m = [], t = new Array(n);
        for (let i = 0; i < n - 1; i++) { dx[i] = p[i + 1].x - p[i].x; m[i] = (p[i + 1].y - p[i].y) / dx[i]; }
        t[0] = m[0]; t[n - 1] = m[n - 2];
        for (let i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
        for (let i = 0; i < n - 1; i++) {
            if (m[i] === 0) { t[i] = t[i + 1] = 0; continue; }
            const a = t[i] / m[i], b = t[i + 1] / m[i], s = a * a + b * b;
            if (s > 9) { const k = 3 / Math.sqrt(s); t[i] = k * a * m[i]; t[i + 1] = k * b * m[i]; }
        }
        let d = `M${p[0].x},${p[0].y}`;
        for (let i = 0; i < n - 1; i++) d += `C${p[i].x + dx[i] / 3},${p[i].y + t[i] * dx[i] / 3} ${p[i + 1].x - dx[i] / 3},${p[i + 1].y - t[i + 1] * dx[i] / 3} ${p[i + 1].x},${p[i + 1].y}`;
        return d;
    }

    let geo = null, activeIdx = null, ready = !intro || total === 0, clipRect = null, tip = null;
    host.classList.toggle('is-empty', total === 0);

    function build() {
        const W = host.clientWidth, H = host.clientHeight; if (!W) return;
        const pad = { l: 48, r: 16, t: 28, b: 28 }, iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
        const tk = ticks(max), top = tk[tk.length - 1];
        const xs = i => pad.l + 10 + (N === 1 ? (iw - 20) / 2 : i * (iw - 20) / (N - 1));
        const ys = v => pad.t + ih - (v / top) * ih;
        const pts = vals.map((v, i) => ({ x: xs(i), y: ys(v) }));
        geo = { W, H, pad, ih, xs, ys };

        let s = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" aria-hidden="true" focusable="false"><defs><clipPath id="dashClip"><rect x="0" y="0" width="${ready ? W : 0}" height="${H}"/></clipPath></defs>`;
        (total === 0 ? [0] : tk).forEach(v => { const y = ys(v); s += `<line class="${v === 0 ? 'dg-base' : 'dg'}" x1="${pad.l}" x2="${W - pad.r}" y1="${y}" y2="${y}"${total === 0 ? ' stroke-dasharray="3 5"' : ''}/>` + (total === 0 ? '' : `<text x="${pad.l - 10}" y="${y + 4}" text-anchor="end">${short(v)}</text>`); });
        const every = Math.max(1, Math.ceil(N / Math.max(2, Math.floor(iw / 78))));
        for (let i = 0; i < N; i++) if ((N - 1 - i) % every === 0) s += `<text x="${xs(i)}" y="${H - 8}" text-anchor="middle">${dShort.format(parse(dates[i]))}</text>`;
        if (total > 0) {
            const line = curve(pts), area = `${line}L${pts[N - 1].x},${ys(0)}L${pts[0].x},${ys(0)}Z`;
            s += `<g clip-path="url(#dashClip)"><path class="d-area" d="${area}"/><path class="d-line" d="${line}"/>`;
            if (N <= 14) pts.forEach(p => { s += `<circle class="d-dot" cx="${p.x}" cy="${p.y}" r="3"/>`; });
            if (N >= 4) { const bx = xs(best), by = ys(max); s += `<circle class="d-best" cx="${bx}" cy="${by}" r="6"/><text class="d-best-t" x="${bx}" y="${by - 12}" text-anchor="${bx > W - 70 ? 'end' : bx < pad.l + 40 ? 'start' : 'middle'}">Tertinggi</text>`; }
            s += `</g><g class="d-hover" style="display:none"><line class="d-cross" y1="${pad.t}" y2="${pad.t + ih}"/><circle class="d-pt" r="5.5"/></g>`;
        }
        s += `</svg>` + (total === 0 ? `<div class="dash-chart-empty"><p>Belum ada pendapatan dalam ${host.dataset.range || N} hari terakhir</p><span>Pesanan yang sudah dikonfirmasi akan muncul di grafik ini.</span></div>` : `<div class="dash-tip" role="presentation"></div>`);
        host.innerHTML = s;
        clipRect = host.querySelector('#dashClip rect'); tip = host.querySelector('.dash-tip');
        if (activeIdx !== null && ready) show(activeIdx, false);
    }

    function show(i, announce) {
        if (!geo || total === 0 || !tip) return; activeIdx = i;
        const x = geo.xs(i), y = geo.ys(vals[i]);
        const g = host.querySelector('.d-hover'); g.style.display = '';
        g.querySelector('.d-cross').setAttribute('x1', x); g.querySelector('.d-cross').setAttribute('x2', x);
        const c = g.querySelector('.d-pt'); c.setAttribute('cx', x); c.setAttribute('cy', y);
        const v = vals[i], diff = avg > 0 ? Math.round((v - avg) / avg * 100) : 0;
        const rel = v === 0 ? 'Tidak ada pendapatan' : (i === best ? 'Hari tertinggi · ' : '') + (diff >= 0 ? '+' : '−') + Math.abs(diff) + '% dari rata-rata';
        tip.innerHTML = `<b>${dLong.format(parse(dates[i]))}</b><strong>${rp(v)}</strong><span>${rel}</span>`;
        const below = y < 96; tip.classList.toggle('is-below', below);
        tip.style.left = Math.min(Math.max(x, 96), geo.W - 96) + 'px'; tip.style.top = y + 'px'; tip.classList.add('is-on');
        if (announce && live) live.textContent = `${dLong.format(parse(dates[i]))}: ${rp(v)}`;
    }
    function hide() { activeIdx = null; const g = host.querySelector('.d-hover'); if (g) g.style.display = 'none'; if (tip) tip.classList.remove('is-on'); }
    const idxAt = e => { const r = host.getBoundingClientRect(), x = e.clientX - r.left; if (N === 1) return 0; const a = geo.xs(0), b = geo.xs(N - 1); return Math.max(0, Math.min(N - 1, Math.round((x - a) / ((b - a) / (N - 1))))); };

    host.addEventListener('pointermove', e => { if (ready && geo) show(idxAt(e), false); });
    host.addEventListener('pointerleave', hide);
    host.addEventListener('focus', () => { if (ready && total > 0) show(activeIdx ?? best, true); });
    host.addEventListener('blur', hide);
    host.addEventListener('keydown', e => {
        if (!ready || total === 0) return; let i = activeIdx ?? best;
        if (e.key === 'ArrowRight') i = Math.min(N - 1, i + 1); else if (e.key === 'ArrowLeft') i = Math.max(0, i - 1);
        else if (e.key === 'Home') i = 0; else if (e.key === 'End') i = N - 1; else if (e.key === 'Escape') { hide(); return; } else return;
        e.preventDefault(); show(i, true);
    });

    build();
    let lastW = host.clientWidth, raf = 0;
    new ResizeObserver(() => { cancelAnimationFrame(raf); raf = requestAnimationFrame(() => { if (host.clientWidth !== lastW) { lastW = host.clientWidth; build(); } }); }).observe(host);

    /* garis menggambar dirinya dari kiri ke kanan */
    if (intro && total > 0 && clipRect) {
        const dur = 1200, delay = 380;
        setTimeout(() => { const t0 = performance.now(); (function step(now) { const p = Math.min(1, (now - t0) / dur); const r = host.querySelector('#dashClip rect'); if (r) r.setAttribute('width', geo.W * easeOut(p)); if (p < 1) requestAnimationFrame(step); else ready = true; })(t0); }, delay);
    }
    try { sessionStorage.setItem('dashIntro', '1'); } catch (e) {}
})();
</script>
@endpush
@endsection
