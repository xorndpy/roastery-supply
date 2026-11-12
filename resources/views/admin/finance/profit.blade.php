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
        ['Biaya operasional', $rp($r['expenses_total']), count($r['expenses_by_category']) . ' kategori', 'text-stone-900'],
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
            <tr class="bg-stone-50 font-semibold"><td class="px-4 py-2.5">Pendapatan bersih</td><td class="px-4 py-2.5 text-right">{{ $rp($r['revenue']) }}</td></tr>
            <tr><td class="px-4 py-2.5 text-stone-600">Harga pokok penjualan (HPP)</td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp(-$r['cogs']) }}</td></tr>
            <tr class="bg-stone-50 font-semibold"><td class="px-4 py-2.5">Laba kotor <span class="font-normal text-stone-500">(margin {{ $pct($r['gross_margin']) }})</span></td><td class="px-4 py-2.5 text-right {{ $r['gross_profit'] < 0 ? 'text-red-600' : '' }}">{{ $rp($r['gross_profit']) }}</td></tr>
            @forelse($r['expenses_by_category'] as $cat => $total)
                <tr><td class="px-4 py-2.5 text-stone-600 pl-8">{{ \App\Models\Expense::CATEGORIES[$cat] ?? $cat }}</td><td class="px-4 py-2.5 text-right text-stone-600">{{ $rp(-$total) }}</td></tr>
            @empty
                <tr><td class="px-4 py-2.5 text-stone-500 pl-8" colspan="2">Belum ada biaya operasional di periode ini.</td></tr>
            @endforelse
            <tr><td class="px-4 py-2.5">Total biaya operasional</td><td class="px-4 py-2.5 text-right">{{ $rp(-$r['expenses_total']) }}</td></tr>
            <tr class="bg-stone-900 text-white font-bold"><td class="px-4 py-3">Laba bersih <span class="font-normal opacity-70">(margin {{ $pct($r['net_margin']) }})</span></td><td class="px-4 py-3 text-right">{{ $rp($r['net_profit']) }}</td></tr>
        </tbody>
    </table>
</div>
<p class="text-xs text-stone-500 mb-8">Catatan: HPP retur belum dikembalikan (refund hanya mengurangi pendapatan). Ongkir yang ditagihkan ke pembeli ({{ $rp($r['shipping_collected']) }}) dianggap diteruskan ke kurir.</p>

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
