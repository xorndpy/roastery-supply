@extends('layouts.admin')

@section('title', 'Pembelian')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $tabs = ['awaiting' => 'Perlu diterima', 'all' => 'Semua', 'draft' => 'Draft', 'ordered' => 'Dipesan', 'partial' => 'Diterima sebagian', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'];
    $pill = ['draft' => 'bg-stone-100 text-stone-700 border-stone-200', 'ordered' => 'bg-blue-50 text-blue-800 border-blue-200', 'partial' => 'bg-amber-50 text-amber-800 border-amber-200', 'received' => 'bg-green-50 text-green-800 border-green-200', 'cancelled' => 'bg-white text-stone-400 border-stone-200'];
@endphp
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Pembelian</h1>
        <p class="mt-1 text-sm text-stone-500">Pesanan ke supplier. Stok dan HPP bertambah saat barang diterima.</p>
    </div>
    @can('manage_stock')<a href="{{ route('admin.purchases.create') }}" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-800">+ Buat pembelian</a>@endcan
</div>

<form method="GET" class="mb-4 flex gap-2">
    <input type="hidden" name="status" value="{{ $status }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari no. PO atau supplier" class="w-full max-w-sm rounded-lg border border-stone-300 px-3 py-2 text-sm">
    <button class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white">Cari</button>
</form>

<div class="mb-4 flex flex-wrap gap-x-5 gap-y-2 border-b border-stone-200 text-sm">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.purchases.index', array_filter(['status' => $key, 'search' => request('search')])) }}" class="-mb-px border-b-2 pb-3 font-medium {{ $status === $key ? 'border-stone-900 text-stone-900' : 'border-transparent text-stone-500 hover:text-stone-800' }}">
            {{ $label }} <span class="ml-1 rounded-full bg-stone-100 px-1.5 text-xs">{{ $stats[$key] ?? 0 }}</span>
        </a>
    @endforeach
</div>

<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-xs text-stone-500">
            <tr><th class="px-4 py-3 font-medium">No. PO</th><th class="px-4 py-3 font-medium">Supplier</th><th class="px-4 py-3 font-medium">Tanggal</th><th class="px-4 py-3 font-medium">Isi</th><th class="px-4 py-3 text-right font-medium">Total</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($purchases as $p)
            <tr class="cursor-pointer hover:bg-stone-50" data-href="{{ route('admin.purchases.show', $p->id) }}">
                <td class="px-4 py-3 font-mono font-medium text-stone-900 whitespace-nowrap">{{ $p->po_number }}</td>
                <td class="px-4 py-3">{{ $p->supplier->name ?? '-' }}</td>
                <td class="px-4 py-3 text-stone-600 whitespace-nowrap">{{ wib($p->ordered_at ?? $p->created_at, 'd M Y') }}</td>
                <td class="px-4 py-3 text-stone-600 whitespace-nowrap">{{ $p->items_count }} item / {{ (int) $p->items_sum_qty_ordered }} unit</td>
                <td class="px-4 py-3 text-right font-semibold whitespace-nowrap">{{ $rp($p->total) }}</td>
                <td class="px-4 py-3"><span class="rounded-md border px-2 py-0.5 text-xs font-medium {{ $pill[$p->status] ?? '' }}">{{ $p->status_label }}</span></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.purchases.show', $p->id) }}" class="row-btn">Buka</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-12 text-center text-stone-500">Belum ada pembelian di tab ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $purchases->links() }}</div>
<script>
    document.querySelectorAll('tr[data-href]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('a, button, input')) window.location = tr.dataset.href; }));
</script>
@endsection
