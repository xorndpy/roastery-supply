@extends('layouts.app')

@section('title', 'Pesanan Saya')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <h1 class="text-4xl font-bold mb-8">Pesanan Saya</h1>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if($orders->count())
        <div class="space-y-4">
            @foreach($orders as $order)
                <div class="bg-white rounded-lg border border-stone-200 p-6">
                    <div class="flex flex-wrap justify-between items-start gap-4 mb-4">
                        <div>
                            <div class="text-xs text-stone-500">No. Pesanan</div>
                            <div class="font-bold text-stone-900">{{ $order->order_number }}</div>
                            <div class="text-xs text-stone-500 mt-1">
                                {{ $order->created_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-stone-500">Total</div>
                            <div class="font-bold text-orange-500 text-lg">
                                Rp {{ number_format($order->total, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>

                    {{-- Items ringkas --}}
                    <div class="text-sm text-stone-600 mb-4">
                        @foreach($order->items as $item)
                            <div>{{ $item->product_name }} ×{{ $item->quantity }}</div>
                        @endforeach
                    </div>

                    {{-- Status --}}
                    <div class="flex flex-wrap justify-between items-center gap-4">
                        @php
                            $statusColors = [
                                'menunggu_bayar' => 'bg-yellow-100 text-yellow-800',
                                'dikonfirmasi' => 'bg-blue-100 text-blue-800',
                                'diproses' => 'bg-purple-100 text-purple-800',
                                'dikirim' => 'bg-indigo-100 text-indigo-800',
                                'selesai' => 'bg-green-100 text-green-800',
                                'batal' => 'bg-red-100 text-red-800',
                            ];
                            $statusLabels = [
                                'menunggu_bayar' => 'Menunggu Bayar',
                                'dikonfirmasi' => 'Dikonfirmasi',
                                'diproses' => 'Diproses',
                                'dikirim' => 'Dikirim',
                                'selesai' => 'Selesai',
                                'batal' => 'Batal',
                            ];
                        @endphp
                        <span class="text-xs px-3 py-1 rounded-full font-medium {{ $statusColors[$order->status] ?? 'bg-stone-100 text-stone-800' }}">
                            {{ $statusLabels[$order->status] ?? $order->status }}
                        </span>

                        <a href="{{ route('customer.orders.show', $order->order_number) }}"
                           class="text-sm text-orange-500 hover:text-orange-600 font-medium">
                            Lihat Detail →
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $orders->links() }}
        </div>
    @else
        <div class="text-center py-16 bg-white rounded-lg border border-stone-200">
            <p class="text-stone-500 mb-4">Belum ada pesanan.</p>
            <a href="{{ route('products.index') }}"
               class="inline-block bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
                Mulai Belanja
            </a>
        </div>
    @endif
</div>
@endsection