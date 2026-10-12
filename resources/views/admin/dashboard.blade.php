@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $statusColors = ['menunggu_bayar' => '#d9a97a', 'dikonfirmasi' => '#a8643a', 'diproses' => '#734027', 'dikirim' => '#4a2f21', 'selesai' => '#5b7f4b', 'batal' => '#b3402f'];
    $statusBadge = [
        'menunggu_bayar' => 'bg-amber-100 text-amber-800', 'dikonfirmasi' => 'bg-orange-100 text-orange-800',
        'diproses' => 'bg-orange-200 text-orange-900', 'dikirim' => 'bg-stone-200 text-stone-800',
        'selesai' => 'bg-green-100 text-green-800', 'batal' => 'bg-red-100 text-red-800',
    ];
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $delta = function ($d) {
        if ($d === null) return '<span class="text-white/60">Belum ada pembanding</span>';
        return ($d >= 0 ? '+' : '') . $d . '% dari periode sebelumnya';
    };
    $statusTotal = max(1, collect($statusChart)->sum('count'));
@endphp

{{-- Header --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-xs text-stone-500">{{ now()->translatedFormat('l, j F Y') }}</p>
        <h1 class="text-2xl text-stone-900">Dashboard</h1>
        <p class="mt-0.5 text-sm text-stone-500">Ringkasan penjualan dan hal yang perlu ditindaklanjuti.</p>
    </div>
    <div class="inline-flex overflow-hidden rounded-md border border-stone-200 bg-white text-sm">
        @foreach([7, 30] as $r)
            <a href="{{ route('admin.dashboard', ['range' => $r]) }}"
               class="px-4 py-2 transition {{ $range === $r ? 'bg-brand text-white' : 'text-stone-600 hover:bg-stone-50' }}">{{ $r }} hari</a>
        @endforeach
    </div>
</div>

@if(!($shippingConfigured ?? true))
    @can('view_shipping_rates')
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <strong>Ongkir belum diatur.</strong> Checkout saat ini berjalan tanpa ongkos kirim.
            <a href="{{ route('admin.shipping.index') }}" class="underline">Atur tarif pengiriman →</a>
        </div>
    @endcan
@endif

{{-- Perlu tindakan --}}
@if(($unreadMessages ?? 0) > 0 || ($pendingTestimonials ?? 0) > 0 || ($pendingReturns ?? 0) > 0 || ($pendingPayments ?? 0) > 0)
    <div class="mb-5 flex flex-wrap gap-2.5 text-sm">
        @if($pendingPayments > 0)
            @can('verify_payments')
                <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-amber-800 hover:bg-amber-100"><strong>{{ $pendingPayments }}</strong> pembayaran menunggu verifikasi →</a>
            @endcan
        @endif
        @if($unreadMessages > 0)
            @can('view_contact_messages')
                <a href="{{ route('admin.messages.index', ['status' => 'unread']) }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-amber-800 hover:bg-amber-100"><strong>{{ $unreadMessages }}</strong> pesan belum dibaca →</a>
            @endcan
        @endif
        @if($pendingReturns > 0)
            @can('view_returns')
                <a href="{{ route('admin.returns.index') }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-amber-800 hover:bg-amber-100"><strong>{{ $pendingReturns }}</strong> retur menunggu →</a>
            @endcan
        @endif
        @if($pendingTestimonials > 0)
            @can('view_testimonials')
                <a href="{{ route('admin.testimonials.index', ['status' => 'pending']) }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-amber-800 hover:bg-amber-100"><strong>{{ $pendingTestimonials }}</strong> testimoni menunggu →</a>
            @endcan
        @endif
    </div>
@endif

{{-- Kartu statistik --}}
<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Pendapatan ' . $range . ' hari', $rp($revenue), $delta($revenueDelta), 'from-[#4a2f21] to-[#6b4531]', 'Total keseluruhan ' . $rp($totalRevenue)],
        ['Pesanan ' . $range . ' hari', number_format($orders), $delta($ordersDelta), 'from-[#8f5230] to-[#b3743f]', $pendingOrders . ' menunggu bayar'],
        ['Produk Aktif', number_format($totalProducts), null, 'from-[#a8814f] to-[#c9a46c]', $lowStockProducts . ' stok menipis'],
        ['Total Pelanggan', number_format($totalCustomers), null, 'from-[#5f5046] to-[#7d6b5e]', $pendingPayments . ' pembayaran pending'],
    ] as [$label, $value, $sub, $grad, $foot])
        <div class="relative overflow-hidden rounded-xl bg-gradient-to-br {{ $grad }} p-5 text-white shadow-sm">
            <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-10 right-6 h-24 w-24 rounded-full bg-white/10"></div>
            <div class="relative">
                <div class="text-xs font-medium uppercase tracking-[0.08em] text-white/75">{{ $label }}</div>
                <div class="mt-2 text-2xl font-semibold">{{ $value }}</div>
                <div class="mt-1 text-xs text-white/80">{!! $sub ?? e($foot) !!}</div>
                @if($sub)<div class="mt-0.5 text-[11px] text-white/60">{{ $foot }}</div>@endif
            </div>
        </div>
    @endforeach
</div>

{{-- Grafik --}}
<div class="mb-6 grid gap-5 lg:grid-cols-3">
    <div class="rounded-xl border border-stone-200 bg-white p-5 lg:col-span-2">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-base">Pendapatan {{ $range }} Hari Terakhir</h2>
                <p class="text-xs text-stone-500">Dari pesanan yang sudah dikonfirmasi</p>
            </div>
            <div class="text-right"><div class="text-lg font-semibold text-orange-600">{{ $rp($revenue) }}</div></div>
        </div>
        <div class="h-64"><canvas id="salesChart"></canvas></div>
    </div>

    <div class="rounded-xl border border-stone-200 bg-white p-5">
        <h2 class="text-base">Status Pesanan</h2>
        <p class="mb-3 text-xs text-stone-500">{{ $range }} hari terakhir</p>
        <div class="relative mx-auto h-44 w-44">
            <canvas id="statusChart"></canvas>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                <div class="text-2xl font-semibold">{{ collect($statusChart)->sum('count') }}</div>
                <div class="text-[11px] text-stone-500">pesanan</div>
            </div>
        </div>
        <ul class="mt-4 space-y-2 text-sm">
            @foreach($statusChart as $s)
                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-stone-600"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $statusColors[$s['key']] }}"></span>{{ $s['label'] }}</span>
                    <span class="font-medium">{{ $s['count'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>

{{-- Tabel + samping --}}
<div class="grid gap-5 lg:grid-cols-3">
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white lg:col-span-2">
        <div class="flex items-center justify-between px-5 py-4">
            <h2 class="text-base">Pesanan Terbaru</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-xs text-orange-600 hover:underline">Lihat semua →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-y border-stone-200 text-left">
                        <th class="px-5 py-2.5">No. Pesanan</th><th class="px-3 py-2.5">Pelanggan</th>
                        <th class="px-3 py-2.5 text-right">Total</th><th class="px-3 py-2.5">Status</th><th class="px-5 py-2.5 text-right">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse($recentOrders as $order)
                        <tr class="hover:bg-stone-50">
                            <td class="px-5 py-3"><a href="{{ route('admin.orders.show', $order->id) }}" class="font-medium text-stone-900 hover:text-orange-600">{{ $order->order_number }}</a></td>
                            <td class="px-3 py-3 text-stone-600">{{ $order->customer->name ?? 'Guest' }}</td>
                            <td class="px-3 py-3 text-right font-medium">{{ $rp($order->total) }}</td>
                            <td class="px-3 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge[$order->status] ?? 'bg-stone-100 text-stone-700' }}">{{ $order->status_label }}</span></td>
                            <td class="px-5 py-3 text-right text-xs text-stone-500">{{ $order->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-stone-500">Belum ada pesanan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-5">
        <div class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="mb-3 text-base">Produk Terlaris</h2>
            @php $maxQty = max(1, (int) ($topProducts->first()->qty ?? 1)); @endphp
            @forelse($topProducts as $p)
                <div class="py-2.5">
                    <div class="flex justify-between gap-3 text-sm"><span class="truncate">{{ $p->name }}</span><b class="shrink-0">{{ (int) $p->qty }} terjual</b></div>
                    <div class="mt-1.5 h-1.5 rounded-full bg-stone-100"><div class="h-1.5 rounded-full bg-orange-500" style="width: {{ $p->qty / $maxQty * 100 }}%"></div></div>
                </div>
            @empty
                <p class="text-sm text-stone-500">Belum ada penjualan pada periode ini.</p>
            @endforelse
        </div>

        <div class="rounded-xl border border-stone-200 bg-white p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base">Stok Menipis</h2>
                <a href="{{ route('admin.stock.index') }}" class="text-xs text-orange-600 hover:underline">Kelola →</a>
            </div>
            @forelse($lowStockList as $product)
                <div class="flex items-center justify-between border-b border-stone-100 py-2.5 last:border-0">
                    <div class="min-w-0"><div class="truncate text-sm font-medium">{{ $product->name }}</div><div class="text-xs text-stone-500">{{ $product->sku }}</div></div>
                    <div class="shrink-0 text-right"><div class="font-semibold text-red-600">{{ $product->stock }}</div><div class="text-[11px] text-stone-500">min {{ $product->stock_threshold }}</div></div>
                </div>
            @empty
                <p class="text-sm text-stone-500">Semua stok aman.</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const rp = v => 'Rp ' + Number(v).toLocaleString('id-ID');
        const line = document.getElementById('salesChart').getContext('2d');
        const grad = line.createLinearGradient(0, 0, 0, 260);
        grad.addColorStop(0, 'rgba(168, 100, 58, .28)');
        grad.addColorStop(1, 'rgba(168, 100, 58, 0)');

        new Chart(line, {
            type: 'line',
            data: { labels: @json($chartLabels), datasets: [{ data: @json($chartData), borderColor: '#a8643a', backgroundColor: grad, borderWidth: 2.5, tension: .4, fill: true, pointRadius: 3, pointBackgroundColor: '#fff', pointBorderColor: '#a8643a', pointHoverRadius: 5 }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => rp(c.parsed.y) } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#7a6a5d', maxTicksLimit: 8 } },
                    y: { beginAtZero: true, grid: { color: '#f3ece4' }, ticks: { color: '#7a6a5d', callback: v => v >= 1e6 ? (v / 1e6) + ' jt' : (v >= 1e3 ? (v / 1e3) + ' rb' : v) } }
                }
            }
        });

        const st = @json($statusChart), colors = @json(array_values($statusColors));
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: { labels: st.map(s => s.label), datasets: [{ data: st.map(s => s.count), backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
            options: { cutout: '72%', plugins: { legend: { display: false } } }
        });
    })();
</script>
@endpush
@endsection
