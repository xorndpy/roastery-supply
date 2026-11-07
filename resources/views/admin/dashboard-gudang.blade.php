@extends('layouts.admin')

@section('title', 'Dashboard Gudang')

@section('content')
@php
    $user = auth()->user();
    $now = now('Asia/Jakarta');
    $hour = (int) $now->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $firstName = \Illuminate\Support\Str::before($user->name ?? 'Gudang', ' ');
    $dateLabel = $now->copy()->locale('id')->translatedFormat('l, j F Y');

    $cards = [
        ['Perlu dikemas', $toPack, 'Sudah dibayar, belum dikemas', route('admin.orders.index', ['status' => 'dikonfirmasi']), 'view_orders', 'text-orange-700'],
        ['Siap dikirim', $toShip, 'Sudah dikemas, belum diserahkan ke kurir', route('admin.orders.index', ['status' => 'diproses']), 'view_orders', 'text-amber-700'],
        ['Dalam pengiriman', $inTransit, 'Sedang di jalan', route('admin.orders.index', ['status' => 'dikirim']), 'view_orders', 'text-stone-800'],
        ['Retur terbuka', $returnsOpen, 'Menunggu keputusan atau barang kembali', route('admin.returns.index'), 'view_returns', 'text-red-700'],
    ];
@endphp

<div class="mb-6">
    <p class="text-sm text-stone-500">{{ $dateLabel }}</p>
    <h1 class="text-2xl font-bold text-stone-900 mt-1">{{ $greeting }}, {{ $firstName }}</h1>
    <p class="text-sm text-stone-600 mt-1">
        @if($toPack + $toShip === 0)
            Antrean kosong. Semua pesanan sudah ditangani.
        @else
            Ada {{ $toPack }} pesanan perlu dikemas dan {{ $toShip }} siap dikirim.
        @endif
    </p>
</div>

{{-- Antrean --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    @foreach($cards as [$label, $count, $hint, $href, $perm, $color])
        @can($perm)
            <a href="{{ $href }}" class="bg-white rounded-lg border border-stone-200 p-5 hover:border-orange-400 transition block">
                <div class="text-sm text-stone-500">{{ $label }}</div>
                <div class="text-3xl font-bold mt-1 {{ $count > 0 ? $color : 'text-stone-300' }}">{{ $count }}</div>
                <div class="text-xs text-stone-500 mt-1">{{ $hint }}</div>
            </a>
        @endcan
    @endforeach
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- Antrean kerja --}}
    @can('view_orders')
    <div class="xl:col-span-2 bg-white rounded-lg border border-stone-200 overflow-x-auto">
        <div class="p-4 border-b border-stone-100 flex justify-between items-center">
            <h2 class="font-bold">Antrean kerja</h2>
            <a href="{{ route('admin.orders.index', ['status' => 'dikonfirmasi']) }}" class="text-sm text-orange-600 hover:underline">Lihat semua</a>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-500">
                <tr><th class="px-4 py-3">Pesanan</th><th class="px-4 py-3">Tujuan</th><th class="px-4 py-3 text-right">Barang</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Menunggu</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse($queue as $o)
                <tr class="hover:bg-stone-50">
                    <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $o->id) }}" class="font-medium text-orange-700 hover:underline">{{ $o->order_number }}</a></td>
                    <td class="px-4 py-3 text-stone-600">{{ $o->shipping_city ?: '-' }}</td>
                    <td class="px-4 py-3 text-right">{{ (int) $o->items->sum('quantity') }} pcs</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs {{ $o->status === 'dikonfirmasi' ? 'bg-orange-100 text-orange-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $o->status === 'dikonfirmasi' ? 'Perlu dikemas' : 'Siap dikirim' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-stone-500">{{ $o->created_at->locale('id')->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Tidak ada pesanan yang menunggu.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @endcan

    {{-- Stok --}}
    @can('view_stock')
    <div class="bg-white rounded-lg border border-stone-200">
        <div class="p-4 border-b border-stone-100 flex justify-between items-center">
            <h2 class="font-bold">Stok perlu perhatian</h2>
            <a href="{{ route('admin.stock.index') }}" class="text-sm text-orange-600 hover:underline">Kelola stok</a>
        </div>
        <div class="grid grid-cols-2 border-b border-stone-100 text-center">
            <div class="p-3 border-r border-stone-100"><div class="text-xl font-bold text-amber-700">{{ $lowStock }}</div><div class="text-xs text-stone-500">Menipis</div></div>
            <div class="p-3"><div class="text-xl font-bold text-red-700">{{ $outOfStock }}</div><div class="text-xs text-stone-500">Habis</div></div>
        </div>
        <ul class="divide-y divide-stone-100 text-sm">
            @forelse($lowStockList as $p)
                <li class="px-4 py-3 flex justify-between gap-3">
                    <span class="min-w-0"><span class="block truncate font-medium">{{ $p->name }}</span><span class="text-xs text-stone-500">{{ $p->sku }}</span></span>
                    <span class="shrink-0 text-right"><span class="font-semibold {{ $p->stock <= 0 ? 'text-red-700' : 'text-amber-700' }}">{{ $p->stock }}</span><span class="block text-xs text-stone-400">min {{ $p->stock_threshold }}</span></span>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-stone-500">Semua stok aman.</li>
            @endforelse
        </ul>
    </div>
    @endcan
</div>

{{-- Pergerakan stok --}}
@can('view_stock')
<div class="bg-white rounded-lg border border-stone-200 mt-6 overflow-x-auto">
    <div class="p-4 border-b border-stone-100 flex justify-between items-center">
        <h2 class="font-bold">Pergerakan stok terbaru</h2>
        <a href="{{ route('admin.stock.history') }}" class="text-sm text-orange-600 hover:underline">Riwayat lengkap</a>
    </div>
    <table class="w-full text-sm">
        <tbody class="divide-y divide-stone-100">
        @forelse($movements as $m)
            @php
                $in = $m->type === 'in' || ($m->type === 'adjustment' && $m->quantity > 0);
                $label = ['in' => 'Masuk', 'out' => 'Keluar', 'adjustment' => 'Penyesuaian'][$m->type] ?? $m->type;
            @endphp
            <tr>
                <td class="px-4 py-3 w-32"><span class="px-2 py-0.5 rounded text-xs {{ $in ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $label }}</span></td>
                <td class="px-4 py-3">{{ $m->product->name ?? '-' }}@if($m->note)<span class="block text-xs text-stone-500">{{ \Illuminate\Support\Str::limit($m->note, 80) }}</span>@endif</td>
                <td class="px-4 py-3 text-right font-semibold {{ $in ? 'text-green-700' : 'text-red-700' }}">{{ $in ? '+' : '-' }}{{ abs($m->quantity) }}</td>
                <td class="px-4 py-3 text-right text-stone-500 whitespace-nowrap">{{ $m->created_at->locale('id')->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td class="px-4 py-8 text-center text-stone-500">Belum ada pergerakan stok.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endcan
@endsection
