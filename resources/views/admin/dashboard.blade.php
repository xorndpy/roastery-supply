@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $user = auth()->user();
    // Semua nominal uang (omzet, refund, grafik, total) hanya untuk yang punya view_finance.
    $canFinance = $user->can('view_finance');
    $statusColors = ['menunggu_bayar' => '#d97706', 'dikonfirmasi' => '#2563eb', 'diproses' => '#7c3aed', 'dikirim' => '#0891b2', 'selesai' => '#16a34a', 'batal' => '#dc2626'];
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    // Versi ringkas untuk kartu KPI: miliaran jadi "Rp 1,37 M" (nilai lengkap tampil di tooltip).
    $rpKpi = fn ($n) => $n >= 1e9 ? 'Rp ' . number_format($n / 1e9, 2, ',', '.') . ' M' : $rp($n);

    // Waktu tampil mengikuti WIB (zona waktu aplikasi tetap UTC agar data lama tidak bergeser).
    $now = now('Asia/Jakarta');
    $hour = (int) $now->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $firstName = \Illuminate\Support\Str::before($user->name ?? 'Admin', ' ');
    // Ganti gambar banner: simpan foto di public/images/dashboard-hero.jpg (atau .webp). Tanpa file, dipakai gradient + pola bawaan.
    $heroImg = collect(['jpg', 'jpeg', 'webp', 'png'])->map(fn ($e) => 'images/dashboard-hero.' . $e)->first(fn ($f) => file_exists(public_path($f)));
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

    // KPI: label, angka, badge perubahan. Perubahan ekstrem (basis periode lalu sangat kecil) dibatasi agar tidak menyesatkan.
    $fmtDelta = fn ($d) => $d > 999 ? '999%+' : abs($d) . '%';
    $kpis = [];
    $pendingPeriod = (int) (collect($statusChart)->firstWhere('key', 'menunggu_bayar')['count'] ?? 0);
    if ($canFinance) {
        $kpis[] = ['icon' => 'coins', 'label' => 'Pendapatan', 'value' => $rpKpi($revenue), 'full' => $rp($revenue), 'delta' => $revenueDelta, 'sub' => 'vs periode lalu'];
    } else {
        $kpis[] = ['icon' => 'box', 'label' => 'Perlu tindakan', 'value' => number_format($taskTotal), 'delta' => null, 'sub' => $taskTotal > 0 ? 'hal menunggu Anda' : 'Semua beres'];
    }
    $kpis[] = ['icon' => 'bag', 'label' => 'Pesanan', 'value' => number_format($orders), 'delta' => $ordersDelta, 'sub' => $pendingPeriod > 0 ? $pendingPeriod . ' belum dibayar' : 'vs periode lalu'];
    if ($canFinance) {
        $kpis[] = ['icon' => 'receipt', 'label' => 'Rata-rata nilai pesanan', 'value' => $rpKpi($aov), 'full' => $rp($aov), 'delta' => $aovDelta, 'sub' => 'dari ' . number_format($paidOrders) . ' pesanan dibayar'];
    } else {
        $kpis[] = ['icon' => 'archive', 'label' => 'Produk aktif', 'value' => number_format($totalProducts), 'delta' => null, 'sub' => $lowStockProducts > 0 ? $lowStockProducts . ' stok menipis' : 'Stok aman'];
    }
    $kpis[] = ['icon' => 'users', 'label' => 'Pelanggan baru', 'value' => number_format($newBuyers), 'delta' => null, 'sub' => $buyers > 0 ? $buyers . ' pembeli · ' . $repeatPct . '% repeat' : 'Belum ada pembeli'];

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
    .td-chart text { font-size: 11px; fill: #6b6b74; }
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

    /* ===== Penyempurnaan tampilan premium ===== */
    .td { --pad: 1.5rem; max-width: 1480px; }
    .td .dash-head { align-items: center; margin-bottom: 1.5rem; position: relative; z-index: 20; }
    .td .dash-h1 { font-size: 1.625rem; font-weight: 650; letter-spacing: -.025em; }
    .td .dash-lede { font-size: 14px; }
    .td-grid { gap: 1.25rem; margin-top: 1.25rem; }
    .td-card { padding: var(--pad); border-color: #ececef; border-radius: 16px; box-shadow: 0 1px 2px rgb(16 24 40 / .03), 0 1px 3px rgb(16 24 40 / .04); }
    .td-head { margin-bottom: 1.1rem; }
    .td-head h2 { font-size: 15px; font-weight: 600; }
    .td-sub { font-size: 13px; }
    .td-card .dash-tablewrap { margin: 0 calc(var(--pad) * -1) calc(var(--pad) * -1); }

    /* KPI: tanpa kotak ikon besar, angka jadi fokus */
    .td-kpis { gap: 1.25rem; }
    .td-kpi { gap: 0; padding: 1.25rem 1.5rem; }
    .td-kl { display: flex; align-items: center; gap: .5rem; font-size: 13px; font-weight: 500; color: var(--ink-2); }
    .td-kl svg { width: 16px; height: 16px; color: var(--accent); flex: none; }
    .td-knum { white-space: nowrap; margin-top: .65rem; font-size: 1.75rem; font-weight: 650; letter-spacing: -.03em; line-height: 1.1; }
    .td-kfoot { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .5rem; margin-top: .7rem; font-size: 12.5px; color: var(--muted); }
    .td-delta { padding: .1rem .45rem; }

    /* Date range picker */
    .rp { position: relative; }
    .rp-btn { display: inline-flex; align-items: center; gap: .6rem; height: 40px; padding: 0 .9rem; border: 1px solid var(--rule); border-radius: 10px; background: #fff; font-size: 13.5px; font-weight: 500; box-shadow: 0 1px 2px rgb(16 24 40 / .04); transition: border-color .15s, box-shadow .15s; }
    .rp-btn:hover { border-color: #d4d4d8; } .rp-btn[aria-expanded="true"] { border-color: var(--accent); box-shadow: 0 0 0 3px rgb(168 100 58 / .12); }
    .rp-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .rp-btn svg { width: 16px; height: 16px; flex: none; color: var(--muted); }
    .rp-btn small { padding-left: .6rem; border-left: 1px solid var(--rule); font-size: 12.5px; font-weight: 400; color: var(--muted); }
    .rp-pop { position: absolute; right: 0; top: calc(100% + 8px); z-index: 40; display: flex; width: max-content; max-width: calc(100vw - 2rem); border: 1px solid var(--rule); border-radius: 16px; background: #fff; box-shadow: 0 20px 48px -12px rgb(16 24 40 / .22), 0 4px 12px rgb(16 24 40 / .06); overflow: hidden; }
    .rp-presets { display: flex; flex-direction: column; gap: 2px; width: 10.5rem; padding: .75rem; border-right: 1px solid var(--rule); background: #fafafa; }
    .rp-presets button { padding: .5rem .7rem; border-radius: 8px; font-size: 13px; text-align: left; color: var(--ink-2); }
    .rp-presets button:hover { background: #f0f0f2; color: var(--ink); }
    .rp-presets button[aria-current="true"] { background: #f6ede6; color: var(--accent-d); font-weight: 600; }
    .rp-body { padding: 1rem 1.1rem 0; width: 19.5rem; }
    .rp-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; font-size: 14px; font-weight: 600; text-transform: capitalize; }
    .rp-nav button { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 8px; color: var(--ink-2); }
    .rp-nav button:hover:not(:disabled) { background: var(--soft); } .rp-nav button:disabled { opacity: .3; cursor: default; }
    .rp-grid { display: grid; grid-template-columns: repeat(7, 1fr); row-gap: 2px; text-align: center; }
    .rp-w { padding-bottom: .35rem; font-size: 11.5px; font-weight: 600; color: var(--muted); }
    .rp-d { position: relative; height: 36px; font-size: 13px; font-variant-numeric: tabular-nums; border-radius: 0; }
    .rp-d span { position: relative; z-index: 1; }
    .rp-d::before { content: ""; position: absolute; inset: 2px 0; background: transparent; border-radius: 0; }
    .rp-d.is-in::before { background: #f6ede6; }
    .rp-d.is-start::before, .rp-d.is-end::before { background: #f6ede6; }
    .rp-d.is-start::before { border-radius: 999px 0 0 999px; left: 2px; } .rp-d.is-end::before { border-radius: 0 999px 999px 0; right: 2px; }
    .rp-d.is-start.is-end::before { border-radius: 999px; left: 2px; right: 2px; }
    .rp-d.is-start span, .rp-d.is-end span { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 999px; background: var(--accent); color: #fff; font-weight: 600; }
    .rp-d.is-out { visibility: hidden; } .rp-d:disabled { color: #c4c4cc; cursor: default; }
    .rp-d:not(:disabled):not(.is-start):not(.is-end):hover span { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 999px; background: var(--soft); }
    .rp-d.is-today span { font-weight: 700; color: var(--accent-d); } .rp-d.is-start.is-today span, .rp-d.is-end.is-today span { color: #fff; }
    .rp-d i { position: absolute; left: 50%; bottom: 3px; z-index: 1; width: 4px; height: 4px; margin-left: -2px; border-radius: 999px; background: var(--accent); opacity: .7; }
    .rp-d.is-start i, .rp-d.is-end i { background: #fff; }
    .rp-d:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; border-radius: 8px; }
    .rp-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin: .75rem -1.1rem 0; padding: .75rem 1.1rem; border-top: 1px solid var(--rule); }
    .rp-foot p { white-space: nowrap; font-size: 12.5px; color: var(--muted); } .rp-foot p b { color: var(--ink); font-weight: 600; }
    .rp-actions { display: flex; gap: .4rem; }
    .rp-actions button { height: 32px; padding: 0 .85rem; border-radius: 8px; font-size: 13px; font-weight: 500; }
    .rp-actions .is-ghost { color: var(--ink-2); } .rp-actions .is-ghost:hover { background: var(--soft); }
    .rp-actions .is-primary { background: var(--accent); color: #fff; } .rp-actions .is-primary:hover:not(:disabled) { background: var(--accent-d); } .rp-actions .is-primary:disabled { opacity: .45; cursor: default; }
    @media (max-width: 640px) { .rp-pop { flex-direction: column; right: auto; left: 0; } .rp-presets { flex-direction: row; flex-wrap: wrap; width: auto; border-right: 0; border-bottom: 1px solid var(--rule); } .rp-body { width: auto; } }

    /* Kartu yang diringkas */
    .td-donut { width: 140px; height: 140px; margin-bottom: 1rem; }
    .td-donut-c b { font-size: 1.5rem; }
    .td-seg { display: inline-flex; padding: 3px; border-radius: 10px; background: #f2f2f4; }
    .td-seg button { padding: .3rem .8rem; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: var(--muted); }
    .td-seg button[aria-pressed="true"] { background: #fff; color: var(--ink); box-shadow: 0 1px 2px rgb(16 24 40 / .12); }
    .td-seg button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .td-sep { margin: 1.1rem 0 .25rem; padding-top: 1rem; border-top: 1px solid #f0f0f2; font-size: 12px; font-weight: 600; color: var(--muted); }
    .td-fu { display: flex; align-items: center; gap: .75rem; padding: .5rem 0; }
    .td-fu + .td-fu { border-top: 1px solid #f6f6f7; }
    @media (prefers-reduced-motion: reduce) { .rp-btn { transition: none; } }

    /* ===== Hero banner ===== */
    .td .td-hero { position: relative; z-index: auto; align-items: center; margin-bottom: 0; padding: 0; }
    .td-hero-bg { position: absolute; inset: 0; z-index: 0; overflow: hidden; border-radius: 20px; background: linear-gradient(115deg, #24150e 0%, #4a2f21 55%, #8f5230 130%); box-shadow: 0 10px 30px -12px rgb(36 21 14 / .55); }
    .td-hero-bg svg { position: absolute; inset: 0; width: 100%; height: 100%; }
    .td-hero-who { position: relative; z-index: 2; display: flex; align-items: center; gap: 1rem; flex: 1 1 22rem; min-width: 0; } .td-hero .rp { flex: none; z-index: 30; }
    .td-hero .dash-h1 { font-size: 1.625rem; }
    .td-kpis ~ .dash-notice { margin: 1.25rem 0 0; }
    .td-kpis { position: relative; z-index: 1; margin-top: 1.25rem; padding: 0; }
    @media (max-width: 640px) { .td-knum { font-size: 1.3rem; } .td-kpi { padding: 1rem; } .td-hero .dash-h1 { font-size: 1.25rem; } .td-kpis { padding: 0; } }
</style>

<div class="dash td">

    {{-- ============ Header + pemilih periode ============ --}}
    <header class="dash-head td-hero">
        <div class="td-hero-who">
            <x-avatar :user="$user" :size="52" :ring="true" />
            <div>
                <h1 class="dash-h1">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="dash-lede">{{ $dateLabel }} · {{ $lede }}</p>
            </div>
        </div>

        <div class="rp" x-data="tdRange(@js(['from' => $fromStr, 'to' => $toStr, 'preset' => $preset, 'today' => $todayStr, 'base' => route('admin.dashboard'), 'cal' => auth()->user()->can('view_orders') ? route('admin.dashboard.calendar') : null]))"
             @keydown.escape.window="close()" @click.outside="close()">
            <button type="button" class="rp-btn" @click="toggle()" :aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog" aria-label="Pilih periode: {{ $periodText }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
                <span>{{ $periodText }}</span>
                <small>{{ $rangeLabel === $periodText ? $days . ' hari' : $rangeLabel }}</small>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <div class="rp-pop" x-show="open" x-cloak x-transition.opacity.duration.120ms role="dialog" aria-label="Pilih periode">
                <div class="rp-presets">
                    <template x-for="p in presets" :key="p.k">
                        <button type="button" @click="pick(p.k)" :aria-current="preset === p.k ? 'true' : 'false'" x-text="p.l"></button>
                    </template>
                </div>
                <div class="rp-body">
                    <div class="rp-nav">
                        <button type="button" @click="shift(-1)" aria-label="Bulan sebelumnya"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>
                        <span x-text="monthLabel" aria-live="polite"></span>
                        <button type="button" @click="shift(1)" :disabled="!canNext" aria-label="Bulan berikutnya"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>
                    </div>
                    <div class="rp-grid" role="group" aria-label="Kalender">
                        <template x-for="w in weekdays" :key="w"><div class="rp-w" aria-hidden="true" x-text="w"></div></template>
                        <template x-for="c in cells" :key="c.date">
                            <button type="button" class="rp-d" :class="cls(c)" :disabled="!c.inMonth || c.date > today" @click="click(c)" @mouseenter="hover = c.date" @mouseleave="hover = null" :aria-label="label(c)" :aria-pressed="(c.date === lo || c.date === hi) ? 'true' : 'false'">
                                <span x-text="c.day"></span><i x-show="c.inMonth && days[c.date]" x-cloak></i>
                            </button>
                        </template>
                    </div>
                    <div class="rp-foot">
                        <p aria-live="polite"><b x-text="summary"></b></p>
                        <div class="rp-actions">
                            <button type="button" class="is-ghost" @click="close()">Batal</button>
                            <button type="button" class="is-primary" :disabled="!a" @click="apply()">Terapkan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- ============ KPI ============ --}}
    <section class="td-kpis" aria-label="Ringkasan {{ $rangeLabel }}">
        @foreach($kpis as $k)
            <div class="td-card td-kpi">
                <p class="td-kl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $ico[$k['icon']] !!}</svg>{{ $k['label'] }}</p>
                <p class="td-knum" @if(isset($k['full'])) title="{{ $k['full'] }}" @endif>{{ $k['value'] }}</p>
                <p class="td-kfoot">
                    @if($k['delta'] !== null)
                        <span class="td-delta {{ $k['delta'] >= 0 ? 'is-up' : 'is-down' }}" title="Dibanding {{ $days }} hari sebelumnya">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="{{ $k['delta'] >= 0 ? '' : 'rotate-180' }}"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
                            {{ $fmtDelta($k['delta']) }}<span class="sr-only">{{ $k['delta'] >= 0 ? ' naik' : ' turun' }}</span>
                        </span>
                    @endif
                    <span>{{ $k['delta'] === null && in_array($k['icon'], ['coins', 'receipt'], true) ? 'Belum ada periode pembanding' : $k['sub'] }}</span>
                </p>
            </div>
        @endforeach
    </section>

    @if(!($shippingConfigured ?? true))
        @can('view_shipping_rates')
            <div class="dash-notice" role="note">
                <svg class="h-5 w-5 shrink-0 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.8l9 15.7H3L12 3.8z"/><path d="M12 10v4M12 17v.1"/></svg>
                <p><strong>Ongkir belum diatur.</strong> Checkout saat ini berjalan tanpa ongkos kirim. <a href="{{ route('admin.shipping.index') }}">Atur tarif pengiriman →</a></p>
            </div>
        @endcan
    @endif

    <div class="td-grid">

        {{-- ============ Penjualan ============ --}}
        <section class="td-card td-c8" aria-labelledby="sales-title">
            <div class="td-head">
                <div>
                    <h2 id="sales-title">Penjualan</h2>
                    <p class="td-sub">{{ $rangeLabel }}@if($rangeLabel !== $periodText) · {{ $periodText }}@endif</p>
                </div>
                @if($canFinance)
                    <div class="td-seg" role="group" aria-label="Metrik grafik">
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
                 aria-label="Grafik batang penjualan {{ $rangeLabel }}. Gunakan panah kiri dan kanan untuk menelusuri tiap hari."
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
                    @foreach($tasks->take(5) as $t)
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
                @if($tasks->count() > 5)<p class="td-sub" style="margin-top:.75rem">+{{ $tasks->count() - 5 }} antrean lainnya</p>@endif
            @else
                <div class="flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                    <div><p class="text-sm font-semibold text-emerald-900">Semua beres</p><p class="text-[12.5px] text-emerald-800/80">Tidak ada yang menunggu tindakan.</p></div>
                </div>
            @endif
        </section>

        {{-- ============ Pesanan terbaru ============ --}}
        <section class="td-card td-c8" aria-labelledby="orders-title">
            <div class="td-head">
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

        {{-- ============ Status pesanan ============ --}}
        <section class="td-card td-c4" aria-labelledby="status-title">
            <div class="td-head"><div><h2 id="status-title">Status pesanan</h2><p class="td-sub">{{ $rangeLabel }}</p></div></div>
            <div class="td-donut" role="img" aria-label="Distribusi status dari {{ $statusTotal }} pesanan">
                <svg viewBox="0 0 160 160" aria-hidden="true">
                    <circle cx="80" cy="80" r="{{ $donutR }}" fill="none" stroke="#f0f0f2" stroke-width="16"/>
                    @if($statusTotal > 0)
                        @foreach($statusChart as $s)
                            @if($s['count'] > 0)
                                @php
                                    $len = $s['count'] / $statusTotal * $donutC;
                                    $gap = $donutParts > 1 ? 3 : 0;
                                @endphp
                                <circle cx="80" cy="80" r="{{ $donutR }}" fill="none" stroke="{{ $statusColors[$s['key']] }}" stroke-width="16"
                                        stroke-dasharray="{{ round(max(0.5, $len - $gap), 2) }} {{ round($donutC, 2) }}" stroke-dashoffset="{{ round(-$donutAcc, 2) }}" transform="rotate(-90 80 80)"/>
                                @php $donutAcc += $len; @endphp
                            @endif
                        @endforeach
                    @endif
                </svg>
                <div class="td-donut-c"><div><b>{{ $statusTotal }}</b><br><span>pesanan</span></div></div>
            </div>
            <ul class="td-legend">
                @foreach(collect($statusChart)->filter(fn ($s) => $s['count'] > 0) as $s)
                    <li>
                        <a href="{{ route('admin.orders.index', ['status' => $s['key']]) }}">
                            <i style="background: {{ $statusColors[$s['key']] }}"></i>
                            <span>{{ $s['label'] }}</span>
                            <b>{{ $s['count'] }}</b>
                            <small>{{ round($s['count'] / $statusTotal * 100) }}%</small>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if($statusTotal === 0)<p class="dash-empty">Belum ada pesanan pada periode ini.</p>@endif
        </section>

        {{-- ============ Produk terlaris & kategori (satu kartu, dua tab) ============ --}}
        <section class="td-card td-c4" aria-labelledby="top-title" x-data="{ tab: 'produk' }">
            <div class="td-head">
                <h2 id="top-title" x-text="tab === 'produk' ? 'Produk terlaris' : 'Penjualan per kategori'">Produk terlaris</h2>
                <div class="td-seg" role="group" aria-label="Tampilan">
                    <button type="button" @click="tab = 'produk'" :aria-pressed="tab === 'produk' ? 'true' : 'false'">Produk</button>
                    <button type="button" @click="tab = 'kategori'" :aria-pressed="tab === 'kategori' ? 'true' : 'false'">Kategori</button>
                </div>
            </div>

            <div x-show="tab === 'produk'">
                @forelse($topProducts as $i => $p)
                    @if($loop->first)<ol class="dash-prods">@endif
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
                    @if($loop->last)</ol>@endif
                @empty
                    <p class="dash-empty">Belum ada penjualan pada periode ini.</p>
                @endforelse
            </div>

            <div x-show="tab === 'kategori'" x-cloak>
                @forelse($categorySales as $i => $c)
                    @if($loop->first)<div class="td-bars">@endif
                    @php $val = $canFinance ? (float) $c->revenue : (float) $c->qty; @endphp
                    <div class="td-bar-row">
                        <div class="top"><span class="truncate">{{ $c->name }}</span><span>{{ $canFinance ? $rp($c->revenue) : (int) $c->qty . ' terjual' }} · {{ round($val / $catTotal * 100) }}%</span></div>
                        <div class="trk" role="presentation"><div class="fil" style="width: {{ max(2, $val / $catTotal * 100) }}%; background: {{ $catColors[$i % count($catColors)] }}"></div></div>
                    </div>
                    @if($loop->last)</div>@endif
                @empty
                    <p class="dash-empty">Belum ada penjualan pada periode ini.</p>
                @endforelse
            </div>
        </section>

        {{-- ============ Stok menipis ============ --}}
        <section class="td-card td-c4" aria-labelledby="stock-title">
            <div class="td-head">
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

        {{-- ============ Pelanggan (ringkasan + follow-up) ============ --}}
        <section class="td-card td-c4" aria-labelledby="cust-title">
            <div class="td-head"><div><h2 id="cust-title">Pelanggan</h2><p class="td-sub">{{ number_format($totalCustomers) }} terdaftar</p></div></div>
            <div class="td-people">
                <div><b>{{ number_format($buyers) }}</b><span>Pembeli</span></div>
                <div><b>{{ number_format($newBuyers) }}</b><span>Baru</span></div>
                <div><b>{{ $repeatPct }}%</b><span>Repeat</span></div>
            </div>
            @if($buyers > 0)
                <div class="td-split" role="img" aria-label="{{ $newBuyers }} pembeli baru dan {{ $buyers - $newBuyers }} pembeli berulang" style="margin-top:1rem">
                    <i style="flex: {{ max($newBuyers, 0.0001) }} 1 0%; background: #a8643a"></i>
                    <i style="flex: {{ max($buyers - $newBuyers, 0.0001) }} 1 0%; background: #2563eb"></i>
                </div>
            @endif

            @can('view_customers')
                <div class="td-sep" style="display:flex;justify-content:space-between;align-items:center">
                    <span>Perlu di-follow-up</span>
                    <a href="{{ route('admin.customers.index', ['segment' => 'dormant']) }}" class="dash-link">{{ $followUpTotal }} dormant →</a>
                </div>
                @forelse($followUps->take(3) as $c)
                    @php
                        $wa = preg_replace('/\D+/', '', (string) $c->phone);
                        if (str_starts_with($wa, '0')) { $wa = '62' . substr($wa, 1); } elseif (str_starts_with($wa, '8')) { $wa = '62' . $wa; }
                        $msg = 'Halo ' . $c->name . ', kami dari ' . \App\Models\Setting::get('site_name', config('app.name')) . '. Sudah cukup lama tidak mampir. Ada kebutuhan alat atau sparepart kopi? Kami siap bantu.';
                    @endphp
                    <div class="td-fu">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $c->name }}</p>
                            <p class="truncate text-xs text-stone-500">Terakhir belanja {{ \Illuminate\Support\Carbon::parse($c->last_paid_at)->locale('id')->diffForHumans() }}@if($canFinance) · {{ $rp($c->lifetime_total) }}@endif</p>
                        </div>
                        @if(strlen($wa) >= 9)
                            <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener"
                               class="shrink-0 rounded-full border border-green-300 bg-green-50 px-3 py-1 text-xs font-medium text-green-800 hover:bg-green-100">WhatsApp</a>
                        @endif
                    </div>
                @empty
                    <p class="dash-empty is-ok" style="margin-top:.5rem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Semua pelanggan aktif dalam 90 hari terakhir.</p>
                @endforelse
            @endcan
        </section>
    </div>
</div>

@push('scripts')
<script>
/* ---------- baris tabel bisa diklik penuh ---------- */
document.querySelectorAll('.dash-table tr[data-href]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('a')) window.location.href = tr.dataset.href; }));

/* ---------- pemilih periode (kalender rentang tanggal, Alpine) ---------- */
window.tdRange = function (cfg) {
    const pad = n => String(n).padStart(2, '0');
    const iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const fmtMonth = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' });
    const fmtShort = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    const fmtNoYear = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short' });
    const fmtLong = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const t = parse(cfg.today), end0 = parse(cfg.to);
    return {
        open: false, today: cfg.today, preset: cfg.preset, y: end0.getFullYear(), m: end0.getMonth() + 1,
        a: cfg.from, b: cfg.to, hover: null, days: {}, loaded: {},
        weekdays: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        presets: [
            { k: 'today', l: 'Hari ini' }, { k: '7', l: '7 hari terakhir' }, { k: '30', l: '30 hari terakhir' },
            { k: '90', l: '90 hari terakhir' }, { k: 'month', l: 'Bulan ini' }, { k: 'lastmonth', l: 'Bulan lalu' },
        ],
        get monthLabel() { return fmtMonth.format(new Date(this.y, this.m - 1, 1)); },
        get canNext() { return this.y < t.getFullYear() || (this.y === t.getFullYear() && this.m < t.getMonth() + 1); },
        get end() { return this.b || this.hover; },
        get lo() { if (!this.a) return null; const e = this.end; return e && e < this.a ? e : this.a; },
        get hi() { if (!this.a) return null; const e = this.end; return e && e > this.a ? e : this.a; },
        get summary() {
            if (!this.a) return 'Pilih tanggal awal';
            if (!this.b) return fmtShort.format(parse(this.a)) + ' → pilih tanggal akhir';
            if (this.a === this.b) return fmtShort.format(parse(this.a));
            const sameYear = this.a.slice(0, 4) === this.b.slice(0, 4);
            return (sameYear ? fmtNoYear : fmtShort).format(parse(this.a)) + ' – ' + fmtShort.format(parse(this.b));
        },
        get cells() {
            const first = new Date(this.y, this.m - 1, 1), lead = (first.getDay() + 6) % 7, dim = new Date(this.y, this.m, 0).getDate(), out = [];
            for (let i = 0; i < lead; i++) out.push({ date: 'x' + i, day: '', inMonth: false });
            for (let d = 1; d <= dim; d++) out.push({ date: this.y + '-' + pad(this.m) + '-' + pad(d), day: d, inMonth: true });
            return out;
        },
        cls(c) {
            if (!c.inMonth) return 'is-out';
            const r = [], lo = this.lo, hi = this.hi;
            if (lo && c.date === lo) r.push('is-start'); if (hi && c.date === hi) r.push('is-end');
            if (lo && hi && c.date > lo && c.date < hi) r.push('is-in');
            if (c.date === this.today) r.push('is-today');
            return r.join(' ');
        },
        label(c) { if (!c.inMonth) return ''; const n = this.days[c.date]; return fmtLong.format(parse(c.date)) + (n ? ', ' + n + ' pesanan' : ''); },
        toggle() { this.open ? this.close() : this.show(); },
        show() { this.a = cfg.from; this.b = cfg.to; this.y = end0.getFullYear(); this.m = end0.getMonth() + 1; this.open = true; this.load(); },
        close() { this.open = false; this.hover = null; },
        shift(d) { const n = new Date(this.y, this.m - 1 + d, 1); this.y = n.getFullYear(); this.m = n.getMonth() + 1; this.load(); },
        click(c) {
            if (!c.inMonth || c.date > this.today) return;
            if (!this.a || this.b) { this.a = c.date; this.b = null; }
            else if (c.date < this.a) { this.b = this.a; this.a = c.date; }
            else this.b = c.date;
        },
        go(params) { window.location.href = cfg.base + '?' + new URLSearchParams(params).toString(); },
        pick(k) { this.go({ range: k }); },
        apply() { if (this.a) this.go({ from: this.a, to: this.b || this.a }); },
        async load() {
            const month = this.y + '-' + pad(this.m);
            if (!cfg.cal || this.loaded[month]) return;
            try {
                const res = await fetch(cfg.cal + '?month=' + month, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!res.ok) return;
                const json = await res.json();
                Object.entries(json.days || {}).forEach(([d, rows]) => { this.days[d] = rows.length; });
                this.loaded[month] = true;
            } catch (e) { /* titik penanda pesanan bersifat tambahan; abaikan bila gagal */ }
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
