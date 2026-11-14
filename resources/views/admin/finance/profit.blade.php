@extends('layouts.admin')

@section('title', 'Laba Rugi')

@php
    $rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.');
    $pct = fn ($n) => $n === null ? '-' : number_format($n, 1, ',', '.') . '%';
    $wib = now('Asia/Jakarta');
    $presets = [
        'Bulan ini' => [$wib->copy()->startOfMonth()->toDateString(), $wib->toDateString()],
        'Bulan lalu' => [$wib->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $wib->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        '30 hari' => [$wib->copy()->subDays(29)->toDateString(), $wib->toDateString()],
        'Tahun ini' => [$wib->copy()->startOfYear()->toDateString(), $wib->toDateString()],
    ];
    $r = $report;
@endphp

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Laba Rugi</h1>
        <p class="text-sm text-stone-500 mt-1">Pesanan yang sudah dibayar, tanggal menurut WIB. Ongkir tidak dihitung sebagai pendapatan.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @can('view_expenses')<a href="{{ route('admin.expenses.index', ['from' => $from, 'to' => $to]) }}" class="border border-stone-300 px-4 py-2 rounded-lg text-sm hover:bg-stone-50">Biaya operasional</a>@endcan
        <a href="{{ route('admin.finance.closings') }}" class="border border-stone-300 px-4 py-2 rounded-lg text-sm hover:bg-stone-50">Tutup buku</a>
        @can('view_costs')<a href="{{ route('admin.finance.costs') }}" class="border border-stone-300 px-4 py-2 rounded-lg text-sm hover:bg-stone-50">HPP produk</a>@endcan
        @can('export_reports')<a href="{{ route('admin.finance.profit.export', ['from' => $from, 'to' => $to]) }}" class="bg-stone-900 text-white px-4 py-2 rounded-lg text-sm">Unduh CSV</a>@endcan
    </div>
</div>

<div class="flex flex-wrap gap-2 mb-3 text-sm">
    @foreach($presets as $label => [$pf, $pt])
        <a href="{{ route('admin.finance.profit', ['from' => $pf, 'to' => $pt]) }}"
           class="px-3 py-1.5 rounded-full border {{ $from === $pf && $to === $pt ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300 text-stone-600 hover:bg-stone-50' }}">{{ $label }}</a>
    @endforeach
</div>
<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap items-end gap-3">
    <div><label class="block text-xs text-stone-500 mb-1">Dari</label><input type="date" name="from" value="{{ $from }}" class="border border-stone-300 rounded-lg px-3 py-2"></div>
    <div><label class="block text-xs text-stone-500 mb-1">Sampai</label><input type="date" name="to" value="{{ $to }}" class="border border-stone-300 rounded-lg px-3 py-2"></div>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Terapkan</button>
</form>

@if($lockedUntil)
    <div class="mb-4 p-3 rounded-lg border border-stone-200 bg-white text-sm text-stone-600">Buku ditutup sampai <strong class="text-stone-900">{{ \Carbon\Carbon::parse($lockedUntil)->format('d M Y') }}</strong>. Biaya pada periode itu terkunci.</div>
@endif

@if($r['missing_cost_qty'] > 0)
    <div class="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50 text-sm text-amber-900">
        <strong>{{ $r['missing_cost_qty'] }} unit</strong> dari {{ $r['missing_cost_products'] }} produk belum punya HPP, jadi dihitung Rp 0 dan laba terlihat lebih besar dari sebenarnya.
        @can('manage_costs')<a href="{{ route('admin.finance.costs', ['filter' => 'kosong']) }}" class="underline font-medium">Isi HPP sekarang</a>@endcan
    </div>
@endif

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Pendapatan bersih', $rp($r['revenue']), $r['order_count'] . ' pesanan dibayar', 'text-stone-900'],
        ['Laba kotor', $rp($r['gross_profit']), 'Margin ' . $pct($r['gross_margin']), $r['gross_profit'] < 0 ? 'text-red-600' : 'text-stone-900'],
        ['Biaya (operasional + gateway)', $rp($r['expenses_total'] + $r['gateway_fees']), count($r['expenses_by_category']) . ' kategori + ' . $r['gateway_count'] . ' transaksi gateway', 'text-stone-900'],
        ['Laba bersih', $rp($r['net_profit']), 'Margin ' . $pct($r['net_margin']), $r['net_profit'] < 0 ? 'text-red-600' : 'text-green-700'],
    ] as [$label, $value, $hint, $color])
        <div class="bg-white p-4 rounded-lg border border-stone-200">
            <div class="text-xs text-stone-500">{{ $label }}</div>
            <div class="text-xl font-bold mt-1 {{ $color }}">{{ $value }}</div>
            <div class="text-xs text-stone-500 mt-1">{{ $hint }}</div>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-lg border border-stone-200 mb-6 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Laporan laba rugi</th><th class="px-4 py-3 text-right">{{ $from }} s/d {{ $to }}</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
            <tr><td class="px-4 py-2.5">Penjualan produk (kotor)</td><td class="px-4 py-2.5 text-right">{{ $rp($r['gross_sales']) }}</td></tr>
            <tr><td class="px-4 py-2.5 text-stone-600">Diskon voucher</td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp(-$r['discount']) }}</td></tr>
            <tr><td class="px-4 py-2.5 font-medium">Penjualan neto</td><td class="px-4 py-2.5 text-right font-medium">{{ $rp($r['net_sales']) }}</td></tr>
            <tr><td class="px-4 py-2.5 text-stone-600">Retur / refund disetujui</td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp(-$r['refund']) }}</td></tr>
            @if($r['service_revenue'] > 0)
                <tr><td class="px-4 py-2.5 text-stone-600">Jasa servis (berbayar)</td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp($r['service_revenue']) }}</td></tr>
            @endif
            @if($r['tax_rate'])
                <tr><td class="px-4 py-2.5 text-stone-600">PPN keluaran {{ rtrim(rtrim(number_format($r['tax_rate'], 2, ',', ''), '0'), ',') }}% <span class="text-xs">(sudah termasuk dalam harga)</span></td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp(-$r['ppn']) }}</td></tr>
            @endif
            <tr class="bg-stone-50 font-semibold"><td class="px-4 py-2.5">Pendapatan bersih</td><td class="px-4 py-2.5 text-right">{{ $rp($r['revenue']) }}</td></tr>
            <tr><td class="px-4 py-2.5 text-stone-600">Harga pokok penjualan (HPP)</td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp(-$r['cogs']) }}</td></tr>
            <tr class="bg-stone-50 font-semibold"><td class="px-4 py-2.5">Laba kotor <span class="font-normal text-stone-500">(margin {{ $pct($r['gross_margin']) }})</span></td><td class="px-4 py-2.5 text-right {{ $r['gross_profit'] < 0 ? 'text-red-600' : '' }}">{{ $rp($r['gross_profit']) }}</td></tr>
            @forelse($r['expenses_by_category'] as $cat => $total)
                <tr><td class="px-4 py-2.5 text-stone-600 pl-8">{{ \App\Models\Expense::CATEGORIES[$cat] ?? $cat }}</td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp(-$total) }}</td></tr>
            @empty
                <tr><td class="px-4 py-2.5 text-stone-500 pl-8" colspan="2">Belum ada biaya operasional di periode ini.</td></tr>
            @endforelse
            <tr><td class="px-4 py-2.5">Total biaya operasional</td><td class="px-4 py-2.5 text-right">{{ $rp(-$r['expenses_total']) }}</td></tr>
            <tr><td class="px-4 py-2.5">Biaya payment gateway <span class="text-xs text-stone-500">({{ $r['gateway_count'] }} transaksi, perkiraan sesuai config/finance.php)</span></td><td class="px-4 py-2.5 text-right">{{ $rp(-$r['gateway_fees']) }}</td></tr>
            <tr class="bg-stone-900 text-white font-bold"><td class="px-4 py-3">Laba bersih <span class="font-normal opacity-70">(margin {{ $pct($r['net_margin']) }})</span></td><td class="px-4 py-3 text-right">{{ $rp($r['net_profit']) }}</td></tr>
        </tbody>
    </table>
</div>
<p class="text-xs text-stone-500 mb-8">Catatan: HPP retur belum dikembalikan (refund hanya mengurangi pendapatan). Ongkir yang ditagihkan ke pembeli ({{ $rp($r['shipping_collected']) }}) dianggap diteruskan ke kurir.</p>

<h2 class="text-lg font-semibold text-stone-900 mb-3">Tren 6 bulan</h2>
@php $trendMax = max(1, ...array_map(fn ($t) => max($t['revenue'], abs($t['net_profit'])), $trend ?: [['revenue' => 1, 'net_profit' => 0]])); @endphp
<div class="bg-white rounded-lg border border-stone-200 mb-8 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3 w-24">Bulan</th><th class="px-4 py-3">Pendapatan bersih</th><th class="px-4 py-3 text-right">Laba kotor</th><th class="px-4 py-3 text-right">Laba bersih</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @foreach($trend as $t)
            <tr>
                <td class="px-4 py-2.5 whitespace-nowrap"><a class="hover:underline" href="{{ route('admin.finance.profit', ['from' => $t['from'], 'to' => $t['to']]) }}">{{ $t['label'] }}</a></td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-3"><div class="h-2.5 rounded bg-orange-400" style="width: {{ max(0, round($t['revenue'] / $trendMax * 100)) }}%; min-width: 2px"></div><span class="text-xs text-stone-600 whitespace-nowrap">{{ $rp($t['revenue']) }}</span></div>
                </td>
                <td class="px-4 py-2.5 text-right">{{ $rp($t['gross_profit']) }}</td>
                <td class="px-4 py-2.5 text-right font-medium {{ $t['net_profit'] < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $rp($t['net_profit']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<h2 class="text-lg font-semibold text-stone-900 mb-3">Laba per produk</h2>
<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Produk</th><th class="px-4 py-3 text-right">Qty</th><th class="px-4 py-3 text-right">Penjualan</th><th class="px-4 py-3 text-right">HPP</th><th class="px-4 py-3 text-right">Laba kotor</th><th class="px-4 py-3 text-right">Margin</th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($r['products'] as $p)
            <tr>
                <td class="px-4 py-2.5">{{ $p['name'] }}@if($p['qty_no_cost'] > 0) <span class="ml-1 text-xs rounded bg-amber-100 text-amber-800 px-1.5 py-0.5">HPP kosong</span>@endif</td>
                <td class="px-4 py-2.5 text-right">{{ $p['qty'] }}</td>
                <td class="px-4 py-2.5 text-right">{{ $rp($p['revenue']) }}</td>
                <td class="px-4 py-2.5 text-right">{{ $rp($p['cogs']) }}</td>
                <td class="px-4 py-2.5 text-right {{ $p['profit'] < 0 ? 'text-red-600' : '' }}">{{ $rp($p['profit']) }}</td>
                <td class="px-4 py-2.5 text-right">{{ $pct($p['margin']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada penjualan terbayar di periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="text-xs text-stone-500 mt-2">Penjualan per produk sebelum diskon voucher (diskon dihitung per pesanan).</p>
@endsection
