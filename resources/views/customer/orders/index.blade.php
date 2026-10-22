@extends('layouts.app')

@section('title', 'Pesanan Saya')

@section('content')
<x-page-header plain title="Pesanan Saya" :crumbs="[['Beranda', route('home')], ['Pesanan Saya']]" />

@php
    $statusBadge = ['menunggu_bayar' => 'bg-amber-100 text-amber-800', 'dikonfirmasi' => 'bg-orange-100 text-orange-800', 'diproses' => 'bg-orange-200 text-orange-900', 'dikirim' => 'bg-stone-200 text-stone-800', 'selesai' => 'bg-green-100 text-green-800', 'batal' => 'bg-red-100 text-red-800'];
    $statusLabels = ['menunggu_bayar' => 'Menunggu Bayar', 'dikonfirmasi' => 'Dikonfirmasi', 'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal'];
@endphp

<div class="container-x py-10">
    @if(session('success'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($orders->count())
        <div class="space-y-4">
            @foreach($orders as $order)
                <div class="rounded-xl border border-stone-200 bg-white p-6 transition hover:border-orange-500/60">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="text-xs text-stone-500">No. Pesanan</div>
                            <div class="font-semibold">{{ $order->order_number }}</div>
                            <div class="mt-0.5 text-xs text-stone-500">{{ $order->created_at->format('d M Y, H:i') }}</div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $statusBadge[$order->status] ?? 'bg-stone-100 text-stone-800' }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
                    </div>

                    <div class="mt-4 space-y-0.5 border-t border-stone-100 pt-4 text-sm text-stone-600">
                        @foreach($order->items as $item)
                            <div>{{ $item->product_name }} <span class="text-stone-400">×{{ $item->quantity }}</span></div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 pt-4">
                        <div><span class="text-xs text-stone-500">Total</span> <span class="ml-1 text-lg font-semibold text-orange-500">Rp {{ number_format($order->total, 0, ',', '.') }}</span></div>
                        <a href="{{ route('customer.orders.show', $order->order_number) }}" class="btn btn-outline btn-sm">Lihat Detail</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">{{ $orders->links() }}</div>
    @else
        <div class="rounded-xl border border-stone-200 bg-white px-6 py-20 text-center">
            <h2 class="heading text-2xl">Belum ada pesanan</h2>
            <p class="mt-1 text-stone-500">Pesanan Anda akan muncul di sini.</p>
            <a href="{{ route('products.index') }}" class="btn btn-primary mt-6">Mulai Belanja</a>
        </div>
    @endif
</div>
@endsection
