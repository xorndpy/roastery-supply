@extends('layouts.admin')

@section('title', 'Pengiriman')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Pengiriman</h1>
    <p class="text-sm text-stone-500 mt-1">Antrean kirim, pesanan dalam perjalanan, dan yang sudah sampai.</p>
</div>

@php
    $tabs = ['ready' => 'Perlu Dikirim', 'shipped' => 'Dalam Pengiriman', 'delivered' => 'Terkirim'];
@endphp

<div class="flex gap-2 mb-4 border-b border-stone-200">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.shipments.index', ['tab' => $key]) }}"
           class="px-4 py-2 -mb-px border-b-2 {{ $tab === $key ? 'border-orange-500 text-stone-900 font-medium' : 'border-transparent text-stone-500 hover:text-stone-700' }}">
            {{ $label }} <span class="text-xs text-stone-400">({{ $counts[$key] }})</span>
        </a>
    @endforeach
</div>

<form method="GET" class="mb-4 flex gap-3">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari no. pesanan{{ $tab === 'ready' ? '' : ' / resi' }}"
           class="flex-1 max-w-sm border border-stone-300 rounded-lg px-3 py-2">
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Cari</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
@if($tab === 'ready')
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-3">Pesanan</th>
                <th class="px-4 py-3">Tujuan</th>
                <th class="px-4 py-3">Barang</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($orders as $o)
            <tr>
                <td class="px-4 py-3">
                    <div class="font-mono font-medium">{{ $o->order_number }}</div>
                    <div class="text-xs text-stone-500">{{ $o->created_at->format('d M Y') }}</div>
                </td>
                <td class="px-4 py-3">
                    <div>{{ $o->shipping_name ?: ($o->customer->name ?? '-') }}</div>
                    <div class="text-xs text-stone-500 max-w-xs">{{ $o->full_shipping_address ?: ($o->customer->address ?? '-') }}</div>
                </td>
                <td class="px-4 py-3">{{ $o->items->sum('quantity') }} unit</td>
                <td class="px-4 py-3 capitalize">{{ $o->status }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.orders.show', $o->id) }}" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-1.5 rounded-lg text-xs">Input Resi</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Tidak ada pesanan yang menunggu dikirim.</td></tr>
        @endforelse
        </tbody>
    </table>
@else
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-3">Pesanan</th>
                <th class="px-4 py-3">Penerima</th>
                <th class="px-4 py-3">Kurir / Resi</th>
                <th class="px-4 py-3">Dikirim</th>
                <th class="px-4 py-3">Sampai</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($shipments as $s)
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.orders.show', $s->order_id) }}" class="font-mono text-orange-600 hover:underline">{{ $s->order->order_number ?? '-' }}</a>
                </td>
                <td class="px-4 py-3">{{ $s->order->shipping_name ?: ($s->order->customer->name ?? '-') }}</td>
                <td class="px-4 py-3">
                    <form method="POST" action="{{ route('admin.shipments.update', $s->id) }}" class="flex flex-wrap gap-1.5">
                        @csrf @method('PATCH')
                        <input type="text" name="courier" value="{{ $s->courier }}" required class="w-20 border border-stone-300 rounded px-2 py-1 text-xs">
                        <input type="text" name="service" value="{{ $s->service }}" placeholder="Layanan" class="w-20 border border-stone-300 rounded px-2 py-1 text-xs">
                        <input type="text" name="tracking_number" value="{{ $s->tracking_number }}" required class="w-36 border border-stone-300 rounded px-2 py-1 text-xs font-mono">
                        <button class="border border-stone-300 hover:bg-stone-50 px-2.5 py-1 rounded text-xs">Simpan</button>
                    </form>
                </td>
                <td class="px-4 py-3 text-stone-500">{{ $s->shipped_at?->format('d M Y') ?? '-' }}</td>
                <td class="px-4 py-3 text-stone-500">{{ $s->delivered_at?->format('d M Y') ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
@endif
</div>

<div class="mt-4">{{ ($orders ?? $shipments)->links() }}</div>

@endsection
