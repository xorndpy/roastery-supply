@extends('layouts.app')

@section('title', 'Dashboard Saya')

@section('content')
@php
    $uid = auth()->id();
    $count = fn ($status = null) => \App\Models\Order::where('user_id', $uid)->when($status, fn ($q) => $q->where('status', $status))->count();
    $recentOrders = \App\Models\Order::where('user_id', $uid)->with('items')->latest()->take(4)->get();
    $statusLabels = ['menunggu_bayar' => 'Menunggu Bayar', 'dikonfirmasi' => 'Dikonfirmasi', 'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal'];
    $statusBadge = ['menunggu_bayar' => 'bg-amber-100 text-amber-800', 'dikonfirmasi' => 'bg-orange-100 text-orange-800', 'diproses' => 'bg-orange-200 text-orange-900', 'dikirim' => 'bg-stone-200 text-stone-800', 'selesai' => 'bg-green-100 text-green-800', 'batal' => 'bg-red-100 text-red-800'];
@endphp

<div class="border-b border-stone-200 bg-stone-100">
    <div class="container-x py-9">
        <div class="eyebrow">Akun Saya</div>
        <h1 class="heading mt-2 text-4xl">Halo, {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}</h1>
        <p class="mt-1.5 text-stone-500">Pantau pesanan, retur, dan data akun Anda di sini.</p>
    </div>
</div>

<div class="container-x space-y-8 py-10">

    {{-- Statistik --}}
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach([
            ['Total Pesanan', $count(), 'from-[#4a2f21] to-[#6b4531]'],
            ['Menunggu Bayar', $count('menunggu_bayar'), 'from-[#8f5230] to-[#b3743f]'],
            ['Pesanan Selesai', $count('selesai'), 'from-[#5f5046] to-[#7d6b5e]'],
        ] as [$label, $value, $grad])
            <div class="relative overflow-hidden rounded-xl bg-gradient-to-br {{ $grad }} p-5 text-white">
                <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10"></div>
                <div class="relative">
                    <div class="text-xs font-medium uppercase tracking-[0.08em] text-white/75">{{ $label }}</div>
                    <div class="mt-2 text-3xl font-semibold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Aksi cepat --}}
    <div class="grid grid-cols-2 gap-3.5 md:grid-cols-4">
        @foreach([
            ['receipt', 'Pesanan Saya', route('customer.orders.index')],
            ['user', 'Edit Profil', route('customer.profile.edit')],
            ['store', 'Belanja Lagi', route('products.index')],
            ['chat', 'Hubungi Kami', route('contact.index')],
        ] as [$icon, $label, $url])
            <a href="{{ $url }}" class="group flex items-center gap-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-orange-500 hover:shadow-md">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-500"><x-ui-icon :name="$icon" class="h-5 w-5" /></span>
                <span class="text-sm font-medium">{{ $label }}</span>
            </a>
        @endforeach
    </div>

    {{-- Pesanan terakhir --}}
    <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <div class="flex items-center justify-between px-6 py-4">
            <h2 class="heading text-lg">Pesanan Terakhir</h2>
            <a href="{{ route('customer.orders.index') }}" class="text-sm text-orange-600 hover:underline">Lihat semua →</a>
        </div>

        @forelse($recentOrders as $order)
            <a href="{{ route('customer.orders.show', $order->order_number) }}"
               class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 px-6 py-4 transition hover:bg-stone-50">
                <div>
                    <div class="text-sm font-medium">{{ $order->order_number }}</div>
                    <div class="text-xs text-stone-500">{{ $order->created_at->format('d M Y') }} · {{ $order->items->sum('quantity') }} item</div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge[$order->status] ?? 'bg-stone-100 text-stone-700' }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
                    <span class="text-sm font-semibold">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </a>
        @empty
            <div class="border-t border-stone-100 px-6 py-12 text-center">
                <p class="text-stone-500">Belum ada pesanan.</p>
                <a href="{{ route('products.index') }}" class="btn btn-primary mt-4">Mulai Belanja</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
