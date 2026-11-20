@php
    $a = $analytics; $pf = $a['profit']; $cmp = $a['compare'];
    $rp = fn ($n) => ($n < 0 ? '- ' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.');
    $chg = function ($v) { return $v === null ? '-' : (($v >= 0 ? '+' : '') . number_format($v, 1, ',', '.') . '%'); };
    $canProfit = auth()->user()->can('view_profit');
@endphp

{{-- Pendapatan vs laba bersih --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500 uppercase mb-2">Pendapatan bersih</div>
        <div class="text-2xl font-bold">{{ $rp($pf['revenue']) }}</div>
        <p class="text-xs text-stone-500 mt-1">Setelah diskon, refund{{ $pf['tax_rate'] ? ', dan PPN' : '' }}. Ongkir tidak dihitung.</p>
    </div>
    @if($canProfit)
    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500 uppercase mb-2">Laba kotor</div>
        <div class="text-2xl font-bold">{{ $rp($pf['gross_profit']) }}</div>
        <p class="text-xs text-stone-500 mt-1">Pendapatan bersih dikurangi HPP{{ $pf['gross_margin'] !== null ? ' - margin ' . number_format($pf['gross_margin'], 1, ',', '.') . '%' : '' }}.</p>
    </div>
    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500 uppercase mb-2">Laba bersih</div>
        <div class="text-2xl font-bold {{ $pf['net_profit'] < 0 ? 'text-red-600' : 'text-stone-900' }}">{{ $rp($pf['net_profit']) }}</div>
        <p class="text-xs text-stone-500 mt-1">Setelah biaya operasional dan biaya payment gateway.</p>
    </div>
    @endif
</div>

{{-- Ringkasan refund, diskon, pembanding --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500 uppercase">Total diskon</div>
        <div class="text-lg font-bold mt-1">{{ $rp($pf['discount']) }}</div>
        <p class="text-xs text-stone-500">{{ $a['voucher_orders'] }} pesanan memakai voucher</p>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500 uppercase">Total refund</div>
        <div class="text-lg font-bold mt-1">{{ $rp($pf['refund']) }}</div>
        <p class="text-xs text-stone-500">{{ $pf['gross_sales'] > 0 ? number_format($pf['refund'] / $pf['gross_sales'] * 100, 1, ',', '.') : '0' }}% dari penjualan kotor</p>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500 uppercase">Rata-rata nilai transaksi</div>
        <div class="text-lg font-bold mt-1">{{ $rp($stats['avg_order_value']) }}</div>
        <p class="text-xs text-stone-500">{{ $chg($cmp['aov']) }} vs periode sebelumnya</p>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500 uppercase">Konversi pesanan</div>
        <div class="text-lg font-bold mt-1">{{ $a['conversion'] !== null ? number_format($a['conversion'], 1, ',', '.') . '%' : '-' }}</div>
        <p class="text-xs text-stone-500">pesanan dibayar dari seluruh pesanan dibuat</p>
    </div>
</div>

<div class="bg-white p-4 rounded-lg border border-stone-200 mb-6 text-sm text-stone-700">
    <b>Perbandingan dengan periode sebelumnya</b> ({{ $cmp['prev_label'] }}):
    pendapatan {{ $chg($cmp['revenue']) }} (sebelumnya {{ $rp($cmp['prev_revenue']) }}),
    jumlah pesanan {{ $chg($cmp['orders']) }} (sebelumnya {{ number_format($cmp['prev_orders']) }}).
</div>

{{-- Grafik bulanan: pendapatan vs laba kotor --}}
<div class="bg-white p-6 rounded-lg border border-stone-200 mb-6">
    <h3 class="font-semibold mb-1">Performa bulanan (12 bulan terakhir)</h3>
    <p class="text-xs text-stone-500 mb-4">Penjualan produk{{ $canProfit ? ' dan laba kotor (penjualan dikurangi HPP)' : '' }}, serta jumlah pesanan.</p>
    <canvas id="monthlyChart" height="90"></canvas>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Kategori --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <h3 class="font-semibold mb-4">Penjualan per kategori</h3>
        <table class="w-full text-sm">
            <thead class="text-xs text-stone-500"><tr><th class="text-left pb-2">Kategori</th><th class="text-right pb-2">Qty</th><th class="text-right pb-2">Penjualan</th>@if($canProfit)<th class="text-right pb-2">Laba kotor</th>@endif</tr></thead>
            <tbody class="divide-y divide-stone-100">
            @forelse($a['by_category'] as $c)
                <tr><td class="py-2">{{ $c->name }}</td><td class="py-2 text-right">{{ number_format($c->qty) }}</td><td class="py-2 text-right">{{ $rp($c->revenue) }}</td>@if($canProfit)<td class="py-2 text-right">{{ $rp($c->revenue - $c->cogs) }}</td>@endif</tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-stone-500">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Produk omzet tertinggi --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <h3 class="font-semibold mb-4">Produk dengan omzet tertinggi</h3>
        <table class="w-full text-sm">
            <thead class="text-xs text-stone-500"><tr><th class="text-left pb-2">Produk</th><th class="text-right pb-2">Qty</th><th class="text-right pb-2">Omzet</th>@if($canProfit)<th class="text-right pb-2">Margin</th>@endif</tr></thead>
            <tbody class="divide-y divide-stone-100">
            @forelse($a['top_revenue'] as $p)
                <tr><td class="py-2">{{ $p['name'] }}</td><td class="py-2 text-right">{{ number_format($p['qty']) }}</td><td class="py-2 text-right">{{ $rp($p['revenue']) }}</td>@if($canProfit)<td class="py-2 text-right">{{ $p['margin'] !== null ? number_format($p['margin'], 1, ',', '.') . '%' : '-' }}</td>@endif</tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-stone-500">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="text-xs text-stone-500 mt-3">Produk dengan jumlah terjual tertinggi ada pada daftar "Produk Terlaris" di atas.</p>
    </div>
</div>

@push('scripts')
<script>
(function () {
    if (!window.Chart) return;
    const rows = @json($a['monthly']);
    const canProfit = @json($canProfit);
    const ds = [{ type: 'bar', label: 'Penjualan', data: rows.map(r => +r.revenue), backgroundColor: '#1c1917', yAxisID: 'y' }];
    if (canProfit) ds.push({ type: 'bar', label: 'Laba kotor', data: rows.map(r => +r.revenue - +r.cogs), backgroundColor: '#d97706', yAxisID: 'y' });
    ds.push({ type: 'line', label: 'Pesanan', data: rows.map(r => +r.orders), borderColor: '#2563eb', tension: 0.3, yAxisID: 'y1' });
    new Chart(document.getElementById('monthlyChart'), {
        data: { labels: rows.map(r => r.label), datasets: ds },
        options: { responsive: true, scales: {
            y: { beginAtZero: true, ticks: { callback: v => v >= 1e6 ? (v / 1e6).toFixed(1) + 'jt' : v } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } } },
            plugins: { tooltip: { callbacks: { label: c => c.dataset.yAxisID === 'y1' ? c.raw + ' pesanan' : 'Rp ' + parseInt(c.raw).toLocaleString('id-ID') } } } }
    });
})();
</script>
@endpush
