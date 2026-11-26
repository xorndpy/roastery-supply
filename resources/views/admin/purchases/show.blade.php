@extends('layouts.admin')

@section('title', $po->po_number)

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $order = ['draft' => 0, 'ordered' => 1, 'partial' => 2, 'received' => 3];
    $at = $order[$po->status] ?? -1;
    $steps = ['Draft', 'Dipesan', 'Sebagian', 'Diterima'];
    $canReceive = in_array($po->status, ['ordered', 'partial'], true);
@endphp
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="{{ route('admin.purchases.index') }}" class="text-sm text-stone-500 hover:text-stone-700">Kembali ke Pembelian</a>
        <h1 class="mt-2 font-mono text-2xl font-bold text-stone-900">{{ $po->po_number }}</h1>
    </div>
    <span class="rounded-md border border-stone-300 bg-white px-3 py-1 text-sm font-medium">{{ $po->status_label }}</span>
</div>

@if($po->status !== 'cancelled')
<div class="mb-5 grid grid-cols-4 text-center text-xs">
    @foreach($steps as $i => $label)
        <div class="border py-2 {{ $i < $at ? 'border-stone-900 bg-stone-900 text-white' : ($i === $at ? 'border-stone-900 font-bold text-stone-900' : 'border-stone-300 bg-white text-stone-500') }}">{{ $label }}</div>
    @endforeach
</div>
@endif

<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <div class="grid gap-4 rounded-lg border border-stone-200 bg-white p-5 sm:grid-cols-3">
            <div><div class="text-xs text-stone-500">Supplier</div><div class="font-medium">{{ $po->supplier->name ?? '-' }}</div></div>
            <div><div class="text-xs text-stone-500">Tanggal pesan</div><div class="font-medium">{{ $po->ordered_at ? wib($po->ordered_at, 'd M Y') : 'Belum dipesan' }}</div></div>
            <div><div class="text-xs text-stone-500">Estimasi tiba</div><div class="font-medium">{{ $po->expected_at ? $po->expected_at->translatedFormat('d M Y') : '-' }}</div></div>
        </div>

        <form method="POST" action="{{ route('admin.purchases.receive', $po->id) }}" @if($canReceive) data-confirm-title="Terima barang?" data-confirm-text="Stok bertambah dan HPP produk dihitung ulang. Penerimaan tidak bisa diurungkan, koreksi lewat Stok." data-confirm-ok="Ya, terima barang" @endif>
            @csrf
            <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 text-left text-xs text-stone-500"><tr><th class="px-4 py-3 font-medium">Produk</th><th class="px-4 py-3 text-right font-medium">Dipesan</th><th class="px-4 py-3 text-right font-medium">Sudah diterima</th><th class="px-4 py-3 text-right font-medium">Harga beli</th><th class="px-4 py-3 text-right font-medium">Subtotal</th>@if($canReceive)<th class="w-32 px-4 py-3 font-medium">Terima sekarang</th>@endif</tr></thead>
                    <tbody class="divide-y divide-stone-100">
                    @foreach($po->items as $i)
                        <tr>
                            <td class="px-4 py-3"><div class="font-medium">{{ $i->product->name ?? '(produk dihapus)' }}</div><div class="text-xs text-stone-500">{{ $i->product->sku ?? '' }}</div></td>
                            <td class="px-4 py-3 text-right">{{ $i->qty_ordered }}</td>
                            <td class="px-4 py-3 text-right">{{ $i->qty_received }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">{{ $rp($i->unit_cost) }}</td>
                            <td class="px-4 py-3 text-right font-medium whitespace-nowrap">{{ $rp($i->qty_ordered * $i->unit_cost) }}</td>
                            @if($canReceive)
                                <td class="px-4 py-3">@if($i->remaining > 0)<input type="number" name="received[{{ $i->id }}]" value="{{ $i->remaining }}" min="0" max="{{ $i->remaining }}" class="w-24 rounded-lg border border-stone-300 px-2 py-1.5 text-sm" aria-label="Terima {{ $i->product->name ?? '' }}">@else<span class="text-xs text-stone-400">Lengkap</span>@endif</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($canReceive)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="max-w-md text-xs text-stone-500">Isi jumlah yang tiba hari ini. Sisanya bisa diterima nanti (status Diterima sebagian). Ongkir/bea dibagi rata menurut nilai barang saat menghitung HPP.</p>
                    <button type="submit" class="rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-stone-800">Terima barang</button>
                </div>
            @endif
        </form>

        @if($po->note)<div class="rounded-lg border border-stone-200 bg-white p-4 text-sm"><div class="mb-1 text-xs text-stone-500">Catatan</div>{{ $po->note }}</div>@endif
    </div>

    <div class="space-y-5">
        <div class="rounded-lg border border-stone-200 bg-white p-5 text-sm">
            <div class="flex justify-between"><span class="text-stone-500">Subtotal</span><span class="whitespace-nowrap">{{ $rp($po->subtotal) }}</span></div>
            <div class="mt-1 flex justify-between"><span class="text-stone-500">Ongkir / bea</span><span class="whitespace-nowrap">{{ $rp($po->shipping_cost) }}</span></div>
            <div class="mt-2 flex justify-between border-t border-stone-100 pt-2 font-bold"><span>Total</span><span class="whitespace-nowrap">{{ $rp($po->total) }}</span></div>
        </div>

        @can('manage_stock')
            @if($po->status === 'draft')
                <div class="space-y-2 rounded-lg border border-stone-200 bg-white p-5">
                    <h3 class="font-bold">Aksi</h3>
                    <form method="POST" action="{{ route('admin.purchases.order', $po->id) }}">@csrf<button class="w-full rounded-lg bg-stone-900 py-2.5 text-sm font-medium text-white hover:bg-stone-800">Tandai dipesan</button></form>
                    <a href="{{ route('admin.purchases.edit', $po->id) }}" class="block w-full rounded-lg border border-stone-300 py-2.5 text-center text-sm hover:bg-stone-50">Ubah draft</a>
                </div>
            @endif
            @if(in_array($po->status, ['draft', 'ordered'], true))
                <form method="POST" action="{{ route('admin.purchases.cancel', $po->id) }}">@csrf
                    <button type="submit" data-confirm-title="Batalkan {{ $po->po_number }}?" data-confirm-text="Pembelian ditandai Dibatalkan. Tidak ada stok yang berubah." data-confirm-ok="Ya, batalkan" data-confirm-tone="danger" class="w-full rounded-lg border border-red-200 py-2 text-sm text-red-700 hover:bg-red-50">Batalkan pembelian</button>
                </form>
            @endif
        @endcan

        <div class="rounded-lg border border-dashed border-stone-300 p-4 text-xs leading-relaxed text-stone-500">
            <b class="text-stone-700">Saat barang diterima:</b> stok produk bertambah, HPP dihitung ulang (rata-rata tertimbang), dan riwayat tercatat di Mutasi Stok sebagai "Pembelian {{ $po->po_number }}". Pembelian barang tidak dicatat sebagai biaya operasional; biayanya masuk laba rugi lewat HPP saat barang terjual.
        </div>
    </div>
</div>
@endsection
