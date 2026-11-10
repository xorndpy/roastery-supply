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
        'receipt' => '<path d="M6 3.5h12v17l-3-2-3 2-3-2-3 2v-17z"/><path d="M9 8.5h6M9 12h6"/>',
        'image' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5"/>',
        'star' => '<path d="M12 3.8l2.5 5.1 5.6.8-4.1 4 1 5.6-5-2.7-5 2.7 1-5.6-4.1-4 5.6-.8L12 3.8z"/>',
    ];

    // Antrean kerja: hanya yang jumlahnya > 0 dan boleh diakses user ini.
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

    // KPI (gaya kartu ringkas: ikon, label, angka, badge perubahan)
    $kpis = [];
    if ($canFinance) {
        $kpis[] = ['icon' => 'coins', 'label' => 'Pendapatan ' . $range . ' hari terakhir', 'value' => $rp($revenue), 'delta' => $revenueDelta, 'sub' => 'dibanding ' . $range . ' hari sebelumnya'];
    } else {
        $kpis[] = ['icon' => 'box', 'label' => 'Perlu tindakan', 'value' => number_format($taskTotal), 'delta' => null, 'sub' => $taskTotal > 0 ? 'hal menunggu Anda' : 'Semua beres'];
    }
    $kpis[] = ['icon' => 'bag', 'label' => 'Pesanan ' . $range . ' hari terakhir', 'value' => number_format($orders), 'delta' => $ordersDelta, 'sub' => $pendingOrders > 0 ? $pendingOrders . ' belum dibayar' : 'Semua sudah dibayar'];
    if ($canFinance) {
        $kpis[] = ['icon' => 'receipt', 'label' => 'Rata-rata nilai pesanan', 'value' => $rp($aov), 'delta' => $aovDelta, 'sub' => 'dari ' . number_format($paidOrders) . ' pesanan dibayar'];
    } else {
        $kpis[] = ['icon' => 'archive', 'label' => 'Produk aktif', 'value' => number_format($totalProducts), 'delta' => null, 'sub' => $lowStockProducts > 0 ? $lowStockProducts . ' stok menipis' : 'Stok aman'];
    }
    $kpis[] = ['icon' => 'users', 'label' => 'Pelanggan baru', 'value' => number_format($newBuyers), 'delta' => null, 'sub' => $buyers > 0 ? $buyers . ' pembeli · ' . $repeatPct . '% repeat order' : 'Belum ada pembeli di periode ini'];

    // Donut status
    $statusTotal = (int) collect($statusChart)->sum('count');
    $donutR = 62;
    $donutC = 2 * M_PI * $donutR;
    $donutAcc = 0;
    $donutParts = count(array_filter($statusChart, fn ($s) => $s['count'] > 0));

    $maxQty = max(1, (int) ($topProducts->first()->qty ?? 1));
    $catTotal = max(1, (float) $categorySales->sum($canFinance ? 'revenue' : 'qty'));
    $catColors = ['#a8643a', '#2563eb', '#16a34a', '#7c3aed', '#d97706'];
@endphp

<style>
    .td-grid { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); margin-top: 1.25rem; }
    @media (min-width: 1180px) {
        .td-grid { grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .td-c4 { grid-column: span 4; } .td-c8 { grid-column: span 8; }
    }
    .td-card { min-width: 0; border: 1px solid var(--rule); border-radius: 16px; background: #fff; padding: 1.25rem; box-shadow: 0 1px 2px rgb(16 24 40 / .04); }
    .td-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
    .td-head h2 { font-size: 1.0625rem; font-weight: 600; letter-spacing: -.01em; }
    .td-sub { margin-top: .15rem; font-size: 12.5px; color: var(--muted); }
    .td-kpis { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 1.25rem; }
    @media (min-width: 1100px) { .td-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; } }
    .td-kpi { display: flex; flex-direction: column; gap: .15rem; }
    .td-kico { display: grid; place-items: center; width: 44px; height: 44px; margin-bottom: .9rem; border-radius: 12px; background: #f6ede6; color: var(--accent-d); }
    .td-kico svg { width: 22px; height: 22px; }
    .td-klabel { font-size: 13px; color: var(--muted); }
    .td-krow { display: flex; align-items: flex-end; justify-content: space-between; gap: .5rem; margin-top: .15rem; }
    .td-knum { font-size: 1.5rem; font-weight: 650; line-height: 1.15; letter-spacing: -.02em; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    @media (min-width: 1500px) { .td-knum { font-size: 1.65rem; } }
    .td-ksub { margin-top: .35rem; font-size: 12px; color: var(--muted); }
    .td-delta { display: inline-flex; align-items: center; gap: .2rem; flex: none; padding: .15rem .5rem; border-radius: 999px; font-size: 12px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .td-delta svg { width: 11px; height: 11px; }
    .td-delta.is-up { background: #ecfdf3; color: #027a48; } .td-delta.is-down { background: #fef3f2; color: #b42318; }

    .td-tabs { display: inline-flex; padding: 3px; border-radius: 10px; background: #f2f2f4; }
    .td-tabs button { padding: .3rem .8rem; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: var(--muted); }
    .td-tabs button[aria-pressed="true"] { background: #fff; color: var(--ink); box-shadow: 0 1px 2px rgb(16 24 40 / .12); }
    .td-tabs button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .td-stats { display: flex; flex-wrap: wrap; gap: .5rem 2rem; margin-bottom: .5rem; }
    .td-stats dt { font-size: 12px; color: var(--muted); } .td-stats dd { font-size: 1.05rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .td-chart { position: relative; height: 280px; outline: none; }
    .td-chart svg { display: block; overflow: visible; }
    .td-chart text { font-size: 11px; fill: #8a8a93; }
    .td-chart .g { stroke: #eeeef0; } .td-chart .gb { stroke: #d4d4d8; }
    .td-chart .bar { fill: var(--accent); transition: opacity .15s; } .td-chart .bar.is-today { fill-opacity: .45; }
    .td-chart .bar.is-dim { opacity: .55; }
    .td-chart .best-t { fill: var(--accent-d); font-weight: 600; }
    .td-tip { position: absolute; z-index: 2; min-width: 168px; padding: 8px 12px; border: 1px solid var(--rule); border-radius: 10px; background: #fff; box-shadow: 0 8px 24px rgb(16 24 40 / .12); font-size: 12px; line-height: 1.45; pointer-events: none; opacity: 0; transform: translate(-50%, calc(-100% - 10px)); transition: opacity .12s; }
    .td-tip.is-on { opacity: 1; } .td-tip b { display: block; font-weight: 600; } .td-tip strong { display: block; font-size: 15px; font-variant-numeric: tabular-nums; } .td-tip span { color: var(--muted); }
    .td-empty { position: absolute; inset: 0 0 28px 40px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: var(--muted); font-size: 13px; }

    .td-donut { position: relative; width: 168px; height: 168px; margin: 0 auto .75rem; }
    .td-donut svg { width: 100%; height: 100%; }
    .td-donut-c { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
    .td-donut-c b { font-size: 1.75rem; font-weight: 650; line-height: 1; } .td-donut-c span { font-size: 12px; color: var(--muted); }
    .td-legend { display: flex; flex-direction: column; }
    .td-legend a { display: flex; align-items: center; gap: .6rem; padding: .4rem .25rem; border-radius: 6px; font-size: 13.5px; }
    .td-legend a:hover { background: var(--soft); } .td-legend i { width: 9px; height: 9px; border-radius: 3px; flex: none; }
    .td-legend span { flex: 1; } .td-legend b { font-variant-numeric: tabular-nums; } .td-legend small { width: 3.2rem; text-align: right; color: var(--muted); font-variant-numeric: tabular-nums; }
    .td-legend a.is-zero { opacity: .45; }

    .td-rank { flex: none; width: 1.25rem; font-size: 12.5px; font-weight: 600; text-align: center; color: var(--muted); }
    .td-bars { display: flex; flex-direction: column; gap: 1rem; }
    .td-bar-row .top { display: flex; justify-content: space-between; gap: .75rem; font-size: 13.5px; } .td-bar-row .top span:last-child { color: var(--ink-2); font-variant-numeric: tabular-nums; flex: none; }
    .td-bar-row .trk { height: 8px; margin-top: .45rem; border-radius: 999px; background: #f0f0f2; overflow: hidden; } .td-bar-row .fil { height: 100%; border-radius: 999px; }

    .td-card .dash-tablewrap { margin: 0 -1.25rem -1.25rem; }
    .td-split { display: flex; height: 8px; gap: 2px; margin: .5rem 0 .85rem; border-radius: 999px; overflow: hidden; background: #f0f0f2; }
    .td-people { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; text-align: center; }
    .td-people b { display: block; font-size: 1.35rem; font-weight: 650; font-variant-numeric: tabular-nums; } .td-people span { font-size: 12px; color: var(--muted); }

    .td-cal-nav { display: inline-flex; align-items: center; gap: .25rem; font-size: 13px; font-weight: 500; }
    .td-cal-nav button { display: grid; place-items: center; width: 28px; height: 28px; border-radius: 8px; color: var(--ink-2); } .td-cal-nav button:hover { background: var(--soft); }
    .td-cal-nav button:focus-visible, .td-cal-d:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
    .td-cal-nav .lbl { min-width: 8.5rem; text-align: center; text-transform: capitalize; }
    .td-cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 2px; text-align: center; }
    .td-cal-w { padding: .25rem 0 .4rem; font-size: 11.5px; font-weight: 600; color: var(--muted); }
    .td-cal-d { position: relative; aspect-ratio: 1 / 1; max-height: 42px; border-radius: 10px; font-size: 13px; font-variant-numeric: tabular-nums; }
    .td-cal-d:disabled { color: #c4c4cc; cursor: default; } .td-cal-d:not(:disabled):hover { background: var(--soft); }
    .td-cal-d.is-today { box-shadow: inset 0 0 0 1.5px var(--accent); font-weight: 600; }
    .td-cal-d.is-sel { background: var(--accent); color: #fff; } .td-cal-d.is-sel:hover { background: var(--accent-d); }
    .td-cal-d i { position: absolute; left: 50%; bottom: 4px; width: 5px; height: 5px; margin-left: -2.5px; border-radius: 999px; background: var(--accent); }
    .td-cal-d.is-sel i { background: #fff; }
    .td-cal-list { margin-top: 1rem; padding-top: .85rem; border-top: 1px solid var(--rule); }
    .td-cal-list li a { display: flex; align-items: center; gap: .6rem; padding: .45rem .35rem; margin: 0 -.35rem; border-radius: 8px; }
    .td-cal-list li a:hover { background: var(--soft); }
    @media (prefers-reduced-motion: reduce) { .td-chart .bar, .td-tip { transition: none; } }
</style>

<div class="dash td">

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
                <p><strong>Ongkir belum diatur.</strong> Checkout saat ini berjalan tanpa ongkos kirim. <a href="{{ route('admin.shipping.index') }}" class="underline underline-offset-4">Atur tarif pengiriman</a></p>
            </div>
        @endcan
    @endif

    {{-- ============ KPI ============ --}}
    <section class="td-kpis" aria-label="Ringkasan">
        @foreach($kpis as $k)
            <div class="td-card td-kpi">
                <span class="td-kico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico[$k['icon']] !!}</svg></span>
                <h2 class="td-klabel">{{ $k['label'] }}</h2>
                <div class="td-krow">
                    <p class="td-knum">{{ $k['value'] }}</p>
                    @if($k['delta'] !== null)
                        <span class="td-delta {{ $k['delta'] >= 0 ? 'is-up' : 'is-down' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $k['delta'] >= 0 ? '' : 'rotate-180' }}"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
                            {{ abs($k['delta']) }}%<span class="sr-only">{{ $k['delta'] >= 0 ? ' naik' : ' turun' }}</span>
                        </span>
                    @endif
                </div>
                <p class="td-ksub">{{ $k['delta'] === null && ($k['icon'] === 'coins' || $k['icon'] === 'receipt') ? 'Belum ada periode pembanding' : $k['sub'] }}</p>
            </div>
        @endforeach
    </section>

    <div class="td-grid">

        {{-- ============ Penjualan (grafik batang) ============ --}}
        <section class="td-card td-c8" aria-labelledby="sales-title">
            <div class="td-head">
                <div>
                    <h2 id="sales-title">Penjualan</h2>
                    <p class="td-sub">{{ $range }} hari terakhir@if($canFinance) · Total sejak toko dibuka {{ $rp($totalRevenue) }}{{ $totalRefund > 0 ? ' (setelah refund ' . $rp($totalRefund) . ')' : '' }}@endif</p>
                </div>
                @if($canFinance)
                    <div class="td-tabs" role="group" aria-label="Metrik grafik">
                        <button type="button" data-metric="rev" aria-pressed="true">Pendapatan</button>
                        <button type="button" data-metric="ord" aria-pressed="false">Pesanan</button>
                    </div>
                @endif
            </div>

            <dl class="td-stats" aria-live="polite">
                <div><dt id="tdStatTotalL">Total</dt><dd id="tdStatTotal">–</dd></div>
                <div><dt title="Rata-rata hari yang sudah selesai (tanpa hari ini)">Rata-rata per hari</dt><dd id="tdStatAvg">–</dd></div>
                <div><dt>Hari tertinggi</dt><dd id="tdStatBest">–</dd></div>
            </dl>

            <div id="tdChart" class="td-chart" tabindex="0" role="group"
                 aria-label="Grafik batang penjualan {{ $range }} hari terakhir. Gunakan panah kiri dan kanan untuk menelusuri tiap hari."
                 data-dates='@json($chartDates)' data-ord='@json($chartOrders)' @if($canFinance) data-rev='@json($chartData)' @endif data-today="{{ $todayStr }}"></div>
            <p id="tdChartLive" class="sr-only" aria-live="polite"></p>
            <table class="sr-only">
                <caption>Penjualan harian</caption>
                <thead><tr><th>Tanggal</th><th>Pesanan</th>@if($canFinance)<th>Pendapatan</th>@endif</tr></thead>
                <tbody>@foreach($chartDates as $i => $d)<tr><td>{{ $d }}</td><td>{{ $chartOrders[$i] }}</td>@if($canFinance)<td>{{ $rp($chartData[$i]) }}</td>@endif</tr>@endforeach</tbody>
            </table>
        </section>

        {{-- ============ Perlu tindakan ============ --}}
        <section class="td-card td-c4" aria-labelledby="queue-title">
            <div class="td-head">
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
                <div class="flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                    <div><p class="text-sm font-semibold text-emerald-900">Semua beres</p><p class="text-[12.5px] text-emerald-800/80">Tidak ada pembayaran, pesanan, atau pesan yang menunggu.</p></div>
                </div>
                <p class="mt-4 text-[12.5px] text-stone-500">Antrean ini terisi otomatis: pembayaran perlu dicek, pesanan siap diproses/dikirim, retur, stok menipis, dan pesan masuk.</p>
            @endif
        </section>

        {{-- ============ Status pesanan (donut) ============ --}}
        <section class="td-card td-c4" aria-labelledby="status-title">
            <div class="td-head">
                <div><h2 id="status-title">Status pesanan</h2><p class="td-sub">{{ $range }} hari terakhir</p></div>
            </div>
            <div class="td-donut" role="img" aria-label="Distribusi status dari {{ $statusTotal }} pesanan">
                <svg viewBox="0 0 160 160" aria-hidden="true">
                    <circle cx="80" cy="80" r="{{ $donutR }}" fill="none" stroke="#f0f0f2" stroke-width="18"/>
                    @if($statusTotal > 0)
                        @foreach($statusChart as $s)
                            @if($s['count'] > 0)
                                @php
                                    $len = $s['count'] / $statusTotal * $donutC;
                                    $gap = $donutParts > 1 ? 3 : 0;
                                @endphp
                                <circle cx="80" cy="80" r="{{ $donutR }}" fill="none" stroke="{{ $statusColors[$s['key']] }}" stroke-width="18"
                                        stroke-dasharray="{{ round(max(0.5, $len - $gap), 2) }} {{ round($donutC, 2) }}" stroke-dashoffset="{{ round(-$donutAcc, 2) }}" transform="rotate(-90 80 80)"/>
                                @php $donutAcc += $len; @endphp
                            @endif
                        @endforeach
                    @endif
                </svg>
                <div class="td-donut-c"><div><b>{{ $statusTotal }}</b><br><span>pesanan</span></div></div>
            </div>
            <ul class="td-legend">
                @foreach($statusChart as $s)
                    <li>
                        <a href="{{ route('admin.orders.index', ['status' => $s['key']]) }}" class="{{ $s['count'] === 0 ? 'is-zero' : '' }}">
                            <i style="background: {{ $statusColors[$s['key']] }}"></i>
                            <span>{{ $s['label'] }}</span>
                            <b>{{ $s['count'] }}</b>
                            <small>{{ $statusTotal > 0 ? round($s['count'] / $statusTotal * 100) : 0 }}%</small>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- ============ Produk terlaris ============ --}}
        <section class="td-card td-c4" aria-labelledby="top-title">
            <div class="td-head"><h2 id="top-title">Produk terlaris</h2><span class="td-sub">{{ $range }} hari</span></div>
            @if($topProducts->isNotEmpty())
                <ol class="dash-prods">
                    @foreach($topProducts as $i => $p)
                        <li>
                            <span class="td-rank">{{ $loop->iteration }}</span>
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
                    @endforeach
                </ol>
            @else
                <p class="dash-empty">Belum ada penjualan dalam {{ $range }} hari terakhir.</p>
            @endif
        </section>

        {{-- ============ Penjualan per kategori ============ --}}
        <section class="td-card td-c4" aria-labelledby="cat-title">
            <div class="td-head"><div><h2 id="cat-title">Penjualan per kategori</h2><p class="td-sub">{{ $canFinance ? 'Berdasarkan pendapatan' : 'Berdasarkan jumlah terjual' }} · {{ $range }} hari</p></div></div>
            @if($categorySales->isNotEmpty())
                <div class="td-bars">
                    @foreach($categorySales as $i => $c)
                        @php $val = $canFinance ? (float) $c->revenue : (float) $c->qty; @endphp
                        <div class="td-bar-row">
                            <div class="top"><span class="truncate">{{ $c->name }}</span><span>{{ $canFinance ? $rp($c->revenue) : (int) $c->qty . ' terjual' }} · {{ round($val / $catTotal * 100) }}%</span></div>
                            <div class="trk" role="presentation"><div class="fil" style="width: {{ max(2, $val / $catTotal * 100) }}%; background: {{ $catColors[$i % count($catColors)] }}"></div></div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="dash-empty">Belum ada penjualan dalam {{ $range }} hari terakhir.</p>
            @endif
        </section>

        {{-- ============ Pelanggan ============ --}}
        <section class="td-card td-c4" aria-labelledby="cust-title">
            <div class="td-head"><div><h2 id="cust-title">Pelanggan</h2><p class="td-sub">Pembeli {{ $range }} hari terakhir</p></div></div>
            <div class="td-people">
                <div><b>{{ number_format($buyers) }}</b><span>Pembeli</span></div>
                <div><b>{{ number_format($newBuyers) }}</b><span>Baru</span></div>
                <div><b>{{ $repeatPct }}%</b><span>Repeat</span></div>
            </div>
            @if($buyers > 0)
                <div class="td-split" role="img" aria-label="{{ $newBuyers }} pembeli baru dan {{ $buyers - $newBuyers }} pembeli berulang">
                    <i style="flex: {{ max($newBuyers, 0.0001) }} 1 0%; background: #a8643a"></i>
                    <i style="flex: {{ max($buyers - $newBuyers, 0.0001) }} 1 0%; background: #2563eb"></i>
                </div>
                <p class="flex gap-4 text-[12px] text-stone-500"><span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-sm" style="background:#a8643a"></i>Baru</span><span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-sm" style="background:#2563eb"></i>Berulang</span></p>
            @else
                <p class="dash-empty mt-4">Belum ada pembeli pada periode ini.</p>
            @endif
            <p class="mt-4 border-t border-stone-100 pt-3 text-[12.5px] text-stone-500">Total pelanggan terdaftar: <strong class="text-stone-800">{{ number_format($totalCustomers) }}</strong></p>
        </section>

        {{-- ============ Pesanan terbaru ============ --}}
        <section class="td-card td-c8" aria-labelledby="orders-title">
            <div class="td-head">
                <h2 id="orders-title">Pesanan terbaru</h2>
                <a href="{{ route('admin.orders.index') }}" class="dash-link">Lihat semua pesanan</a>
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

        {{-- ============ Kalender pesanan ============ --}}
        @can('view_orders')
        <section class="td-card td-c4" aria-labelledby="cal-title"
                 x-data="tdCalendar(@js(route('admin.dashboard.calendar')), @js($canFinance), @js($todayStr), @js($statusColors))" x-init="load()">
            <div class="td-head" style="margin-bottom:.75rem">
                <h2 id="cal-title">Kalender</h2>
                <div class="td-cal-nav">
                    <button type="button" @click="shift(-1)" aria-label="Bulan sebelumnya"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>
                    <span class="lbl" x-text="monthLabel" aria-live="polite"></span>
                    <button type="button" @click="shift(1)" aria-label="Bulan berikutnya"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
                </div>
            </div>

            <div class="td-cal" role="grid" aria-labelledby="cal-title">
                <template x-for="w in weekdays" :key="w"><div class="td-cal-w" role="columnheader" x-text="w"></div></template>
                <template x-for="c in cells" :key="c.date">
                    <button type="button" class="td-cal-d" role="gridcell" :disabled="!c.inMonth" @click="select(c.date)"
                            :class="{ 'is-today': c.date === today, 'is-sel': c.date === selected }"
                            :aria-label="label(c)" :aria-pressed="c.date === selected ? 'true' : 'false'">
                        <span x-text="c.day"></span>
                        <i x-show="c.inMonth && count(c.date) > 0" x-cloak></i>
                    </button>
                </template>
            </div>

            <div class="td-cal-list" aria-live="polite">
                <p class="text-xs font-semibold text-stone-500" x-text="selectedLabel"></p>
                <p class="mt-2 text-[13px] text-stone-500" x-show="!error &amp;&amp; selected &amp;&amp; selectedOrders.length === 0" x-cloak>Tidak ada pesanan pada tanggal ini.</p>
                <p class="mt-2 text-[13px] text-stone-500" x-show="!error &amp;&amp; !selected" x-cloak>Pilih tanggal untuk melihat pesanannya.</p>
                <ul class="mt-1">
                    <template x-for="o in selectedOrders.slice(0, 5)" :key="o.n">
                        <li>
                            <a :href="o.u">
                                <i class="h-2 w-2 shrink-0 rounded-full" :style="'background:' + (colors[o.s] || '#71717a')"></i>
                                <span class="min-w-0 flex-1">
                                    <b class="block truncate text-[13px] font-semibold" x-text="o.n"></b>
                                    <small class="block truncate text-[12px] text-stone-500" x-text="o.h + ' WIB · ' + o.c + ' · ' + o.l"></small>
                                </span>
                                <span class="shrink-0 text-[12.5px] font-medium tabular-nums" x-show="finance && o.t !== undefined" x-text="rp(o.t)"></span>
                            </a>
                        </li>
                    </template>
                </ul>
                <p class="mt-1 text-xs text-stone-500" x-show="selectedOrders.length > 5" x-cloak x-text="'+' + (selectedOrders.length - 5) + ' pesanan lainnya di tanggal ini'"></p>
            </div>
        </section>
        @endcan

        {{-- ============ Stok menipis ============ --}}
        <section class="td-card td-c4" aria-labelledby="stock-title">
            <div class="td-head">
                <h2 id="stock-title">Stok menipis</h2>
                @can('view_stock')<a href="{{ route('admin.stock.index') }}" class="dash-link">Kelola stok</a>@endcan
            </div>
            @if($lowStockList->isNotEmpty())
                <ul class="dash-stock">
                    @foreach($lowStockList as $product)
                        @php $pimg = $product->images->first()->image ?? null; @endphp
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
                    @endforeach
                </ul>
            @else
                <p class="dash-empty is-ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Semua stok di atas batas minimum.</p>
            @endif
        </section>

        {{-- ============ Pembayaran online ============ --}}
        @can('verify_payments')
        <section class="td-card td-c4" aria-labelledby="auto-title">
            <div class="td-head">
                <div>
                    <h2 id="auto-title">Pembayaran online</h2>
                    <p class="td-sub">{{ $autoPaymentsWeek }} dikonfirmasi otomatis (7 hari)</p>
                </div>
                <a href="{{ route('admin.payments.index', ['tab' => 'otomatis']) }}" class="dash-link">Lihat pembayaran otomatis</a>
            </div>
            @if($recentGatewayPayments->isNotEmpty())
                <ul class="dash-stock">
                    @foreach($recentGatewayPayments as $gp)
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
                    @endforeach
                </ul>
            @else
                <p class="dash-empty">Belum ada pembayaran online.</p>
            @endif
        </section>
        @endcan

        {{-- ============ Follow-up pelanggan ============ --}}
        @can('view_customers')
        <section class="td-card td-c4" aria-labelledby="fu-title">
            <div class="td-head">
                <h2 id="fu-title">Follow-up pelanggan</h2>
                <a href="{{ route('admin.customers.index', ['segment' => 'dormant']) }}" class="dash-link">Lihat {{ $followUpTotal }} customer dormant</a>
            </div>
            @if($followUps->isNotEmpty())
                <ul class="dash-stock">
                    @foreach($followUps as $c)
                        @php
                            $wa = preg_replace('/\D+/', '', (string) $c->phone);
                            if (str_starts_with($wa, '0')) { $wa = '62' . substr($wa, 1); } elseif (str_starts_with($wa, '8')) { $wa = '62' . $wa; }
                            $msg = 'Halo ' . $c->name . ', kami dari ' . \App\Models\Setting::get('site_name', config('app.name')) . '. Sudah cukup lama tidak mampir. Ada kebutuhan alat atau sparepart kopi? Kami siap bantu.';
                        @endphp
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
                    @endforeach
                </ul>
            @else
                <p class="dash-empty is-ok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Semua pelanggan berbelanja dalam 90 hari terakhir.</p>
            @endif
        </section>
        @endcan
    </div>
</div>

@push('scripts')
<script>
/* ---------- baris tabel bisa diklik penuh ---------- */
document.querySelectorAll('.dash-table tr[data-href]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('a')) window.location.href = tr.dataset.href; }));

/* ---------- kalender pesanan (Alpine) ---------- */
window.tdCalendar = function (url, finance, today, colors) {
    const nf = new Intl.NumberFormat('id-ID');
    const fmtMonth = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
    const fmtLong = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const pad = n => String(n).padStart(2, '0');
    const iso = (y, m, d) => y + '-' + pad(m) + '-' + pad(d);
    const [ty, tm] = today.split('-').map(Number);
    return {
        url, finance, today, colors, y: ty, m: tm, days: {}, selected: today, error: false,
        weekdays: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        rp(v) { return 'Rp ' + nf.format(Math.round(v)); },
        get monthLabel() { return fmtMonth.format(new Date(this.y, this.m - 1, 1)); },
        get cells() {
            const first = new Date(this.y, this.m - 1, 1), lead = (first.getDay() + 6) % 7, dim = new Date(this.y, this.m, 0).getDate();
            const out = [];
            for (let i = lead; i > 0; i--) { const d = new Date(this.y, this.m - 1, 1 - i); out.push({ date: iso(d.getFullYear(), d.getMonth() + 1, d.getDate()), day: d.getDate(), inMonth: false }); }
            for (let d = 1; d <= dim; d++) out.push({ date: iso(this.y, this.m, d), day: d, inMonth: true });
            while (out.length % 7) { const d = new Date(this.y, this.m, out.length - lead - dim + 1); out.push({ date: iso(d.getFullYear(), d.getMonth() + 1, d.getDate()), day: d.getDate(), inMonth: false }); }
            return out;
        },
        count(date) { return (this.days[date] || []).length; },
        label(c) { const n = this.count(c.date); return fmtLong.format(new Date(c.date + 'T00:00:00')) + (c.inMonth ? (n ? ', ' + n + ' pesanan' : ', tidak ada pesanan') : ''); },
        get selectedOrders() { return this.selected ? (this.days[this.selected] || []).slice().reverse() : []; },
        get selectedLabel() { if (!this.selected) return ''; const n = this.count(this.selected); return fmtLong.format(new Date(this.selected + 'T00:00:00')) + (n ? ' · ' + n + ' pesanan' : ''); },
        select(date) { this.selected = date; },
        shift(delta) {
            const d = new Date(this.y, this.m - 1 + delta, 1);
            this.y = d.getFullYear(); this.m = d.getMonth() + 1;
            this.selected = (this.y === ty && this.m === tm) ? today : null;
            this.load();
        },
        async load() {
            const month = this.y + '-' + pad(this.m);
            try {
                const res = await fetch(this.url + '?month=' + month, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!res.ok) throw new Error(res.status);
                const json = await res.json();
                if (json.month === month) { this.days = json.days || {}; this.error = false; }
            } catch (e) { this.error = true; }
        },
    };
};

/* ---------- grafik batang penjualan (SVG buatan sendiri) ---------- */
(function () {
    const host = document.getElementById('tdChart'); if (!host) return;
    const live = document.getElementById('tdChartLive');
    const nf = new Intl.NumberFormat('id-ID');
    const rp = v => 'Rp ' + nf.format(Math.round(v));
    const num1 = x => String(Number(x.toFixed(1))).replace('.', ',');
    const short = v => v >= 1e9 ? num1(v / 1e9) + ' M' : v >= 1e6 ? num1(v / 1e6) + ' jt' : v >= 1e3 ? num1(v / 1e3) + ' rb' : String(v);
    const dates = JSON.parse(host.dataset.dates), today = host.dataset.today;
    const series = { ord: JSON.parse(host.dataset.ord).map(Number) };
    if (host.dataset.rev) series.rev = JSON.parse(host.dataset.rev).map(Number);
    const N = dates.length;
    const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const dShort = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
    const dLong = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });
    const ticks = m => { if (m <= 0) return [0, 1]; const raw = m / 4, mag = Math.pow(10, Math.floor(Math.log10(raw))); const step = [1, 2, 2.5, 5, 10].map(k => k * mag).find(s => s >= raw); const n = Math.ceil(m / step - 1e-9); return Array.from({ length: n + 1 }, (_, i) => i * step); };

    let metric = series.rev ? 'rev' : 'ord', geo = null, active = null, tip = null;
    const fmt = v => metric === 'rev' ? rp(v) : nf.format(v) + ' pesanan';
    const axis = v => metric === 'rev' ? short(v) : String(v);

    function stats() {
        const vals = series[metric], total = vals.reduce((a, b) => a + b, 0);
        const done = N > 1 ? vals.slice(0, -1) : vals, avg = done.reduce((a, b) => a + b, 0) / Math.max(1, done.length);
        const max = Math.max(0, ...vals), best = vals.indexOf(max);
        document.getElementById('tdStatTotalL').textContent = metric === 'rev' ? 'Total pendapatan' : 'Total pesanan';
        document.getElementById('tdStatTotal').textContent = metric === 'rev' ? rp(total) : nf.format(total);
        document.getElementById('tdStatAvg').textContent = metric === 'rev' ? rp(avg) : num1(avg);
        document.getElementById('tdStatBest').textContent = max > 0 ? dShort.format(parse(dates[best])) + ' · ' + (metric === 'rev' ? short(max) : nf.format(max)) : '–';
    }

    function build() {
        const vals = series[metric], total = vals.reduce((a, b) => a + b, 0), max = Math.max(0, ...vals), best = vals.indexOf(max);
        const W = host.clientWidth, H = host.clientHeight; if (!W) return;
        const pad = { l: metric === 'rev' ? 52 : 34, r: 6, t: 22, b: 26 }, iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
        const tk = ticks(max), top = tk[tk.length - 1], slot = iw / N, bw = Math.max(3, Math.min(40, slot * 0.62));
        const xc = i => pad.l + slot * i + slot / 2, y = v => pad.t + ih - (v / top) * ih;
        geo = { W, H, pad, slot, xc, y, N };

        let s = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" aria-hidden="true" focusable="false">`;
        (total === 0 ? [0] : tk).forEach(v => { const yy = y(v); s += `<line class="${v === 0 ? 'gb' : 'g'}" x1="${pad.l}" x2="${W - pad.r}" y1="${yy}" y2="${yy}"${total === 0 ? ' stroke-dasharray="3 5"' : ''}/>` + (total === 0 ? '' : `<text x="${pad.l - 8}" y="${yy + 4}" text-anchor="end">${axis(v)}</text>`); });
        const every = Math.max(1, Math.ceil(N / Math.max(2, Math.floor(iw / 62))));
        for (let i = 0; i < N; i++) if ((N - 1 - i) % every === 0) s += `<text x="${xc(i)}" y="${H - 6}" text-anchor="middle">${dShort.format(parse(dates[i]))}</text>`;
        vals.forEach((v, i) => {
            if (v <= 0) return;
            const h = Math.max(2, (v / top) * ih), x0 = xc(i) - bw / 2, y0 = y(0), yt = y0 - h, r = Math.min(6, bw / 2, h);
            s += `<path class="bar${dates[i] === today ? ' is-today' : ''}" data-i="${i}" d="M${x0},${y0}V${yt + r}Q${x0},${yt} ${x0 + r},${yt}H${x0 + bw - r}Q${x0 + bw},${yt} ${x0 + bw},${yt + r}V${y0}Z"/>`;
        });
        if (total > 0 && N >= 4 && dates[best] !== today) { const bx = xc(best), by = y(max); s += `<text class="best-t" x="${bx}" y="${by - 8}" text-anchor="${bx > W - 60 ? 'end' : bx < pad.l + 30 ? 'start' : 'middle'}">${metric === 'rev' ? short(max) : max}</text>`; }
        s += '</svg>';
        s += total === 0 ? `<div class="td-empty"><p>Belum ada ${metric === 'rev' ? 'pendapatan' : 'pesanan'} dalam ${N} hari terakhir</p></div>` : '<div class="td-tip" role="presentation"></div>';
        host.innerHTML = s; tip = host.querySelector('.td-tip');
        if (active !== null) show(active, false);
    }

    function show(i, announce) {
        if (!geo || !tip) return; active = i;
        const vals = series[metric], v = vals[i], x = geo.xc(i), yy = geo.y(v);
        host.querySelectorAll('.bar').forEach(b => b.classList.toggle('is-dim', Number(b.dataset.i) !== i));
        const isToday = dates[i] === today;
        let rel = isToday ? 'Hari ini (belum selesai)' : (v === 0 ? 'Tidak ada ' + (metric === 'rev' ? 'pendapatan' : 'pesanan') : '');
        if (!rel && metric === 'rev') { const done = vals.slice(0, -1), avg = done.reduce((a, b) => a + b, 0) / Math.max(1, done.length); if (avg > 0) { const d = Math.round((v - avg) / avg * 100); rel = (d >= 0 ? '+' : '−') + Math.abs(d) + '% dari rata-rata'; } }
        tip.innerHTML = `<b>${dLong.format(parse(dates[i]))}</b><strong>${fmt(v)}</strong>${rel ? `<span>${rel}</span>` : ''}`;
        tip.style.left = Math.min(Math.max(x, 96), geo.W - 96) + 'px'; tip.style.top = Math.max(yy, 56) + 'px'; tip.classList.add('is-on');
        if (announce && live) live.textContent = `${dLong.format(parse(dates[i]))}: ${fmt(v)}`;
    }
    function hide() { active = null; host.querySelectorAll('.bar').forEach(b => b.classList.remove('is-dim')); if (tip) tip.classList.remove('is-on'); }
    const idxAt = e => { const r = host.getBoundingClientRect(); return Math.max(0, Math.min(N - 1, Math.floor((e.clientX - r.left - geo.pad.l) / geo.slot))); };

    host.addEventListener('pointermove', e => { if (geo && Math.max(0, ...series[metric]) > 0) show(idxAt(e), false); });
    host.addEventListener('pointerleave', hide);
    host.addEventListener('focus', () => { if (Math.max(0, ...series[metric]) > 0) show(active ?? N - 1, true); });
    host.addEventListener('blur', hide);
    host.addEventListener('keydown', e => {
        if (Math.max(0, ...series[metric]) === 0) return; let i = active ?? N - 1;
        if (e.key === 'ArrowRight') i = Math.min(N - 1, i + 1); else if (e.key === 'ArrowLeft') i = Math.max(0, i - 1);
        else if (e.key === 'Home') i = 0; else if (e.key === 'End') i = N - 1; else if (e.key === 'Escape') { hide(); return; } else return;
        e.preventDefault(); show(i, true);
    });

    document.querySelectorAll('[data-metric]').forEach(b => b.addEventListener('click', () => {
        metric = b.dataset.metric; active = null;
        document.querySelectorAll('[data-metric]').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
        stats(); build();
    }));

    stats(); build();
    let lastW = host.clientWidth, raf = 0;
    new ResizeObserver(() => { cancelAnimationFrame(raf); raf = requestAnimationFrame(() => { if (host.clientWidth !== lastW) { lastW = host.clientWidth; build(); } }); }).observe(host);
})();
</script>
@endpush
@endsection