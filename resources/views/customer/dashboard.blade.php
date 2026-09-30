@extends('layouts.app')

@section('title', 'Dashboard Saya')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    {{-- Greeting --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-stone-900">
            Halo, {{ auth()->user()->name }} 👋
        </h1>
        <p class="text-stone-600 mt-2">Selamat datang di dashboard akun kamu.</p>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm text-stone-500">Total Pesanan</div>
                <div class="text-2xl">🛒</div>
            </div>
            <div class="text-2xl font-bold">{{ \App\Models\Order::where('user_id', auth()->id())->count() }}</div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm text-stone-500">Menunggu Bayar</div>
                <div class="text-2xl">⏳</div>
            </div>
            <div class="text-2xl font-bold">
                {{ \App\Models\Order::where('user_id', auth()->id())->where('status', 'menunggu_bayar')->count() }}
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <div class="flex items-center justify-between mb-2">
                <div class="text-sm text-stone-500">Selesai</div>
                <div class="text-2xl">✅</div>
            </div>
            <div class="text-2xl font-bold">
                {{ \App\Models\Order::where('user_id', auth()->id())->where('status', 'selesai')->count() }}
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200 mb-8">
        <h2 class="font-bold mb-4">Aksi Cepat</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="{{ route('customer.orders.index') }}" class="p-4 border border-stone-200 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition text-center">
                <div class="text-2xl mb-2">📋</div>
                <div class="text-sm font-medium">Pesanan Saya</div>
            </a>
            <a href="{{ route('customer.profile.edit') }}" class="p-4 border border-stone-200 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition text-center">
                <div class="text-2xl mb-2">👤</div>
                <div class="text-sm font-medium">Edit Profil</div>
            </a>
            <a href="{{ route('products.index') }}" class="p-4 border border-stone-200 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition text-center">
                <div class="text-2xl mb-2">☕</div>
                <div class="text-sm font-medium">Belanja Lagi</div>
            </a>
            <a href="{{ route('contact.index') }}" class="p-4 border border-stone-200 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition text-center">
                <div class="text-2xl mb-2">💬</div>
                <div class="text-sm font-medium">Hubungi Kami</div>
            </a>
        </div>
    </div>

    {{-- Recent Orders --}}
    @php
        $recentOrders = \App\Models\Order::where('user_id', auth()->id())
            ->with('items')
            ->latest()
            ->take(3)
            ->get();
    @endphp

    @if($recentOrders->count())
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <div class="flex justify-between items-center mb-4">
                <h2 class="font-bold">Pesanan Terakhir</h2>
                <a href="{{ route('customer.orders.index') }}" class="text-sm text-orange-500 hover:text-orange-600">
                    Lihat Semua →
                </a>
            </div>
            <div class="space-y-3">
                @foreach($recentOrders as $order)
                    <a href="{{ route('customer.orders.show', $order->order_number) }}"
                       class="flex justify-between items-center p-3 hover:bg-stone-50 rounded-lg transition border border-stone-100">
                        <div>
                            <div class="font-medium text-sm">{{ $order->order_number }}</div>
                            <div class="text-xs text-stone-500">{{ $order->created_at->format('d M Y') }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium text-sm">Rp {{ number_format($order->total, 0, ',', '.') }}</div>
                            <div class="text-xs text-stone-500">{{ $order->status }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection