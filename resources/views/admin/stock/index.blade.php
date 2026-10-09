@extends('layouts.admin')

@section('title', 'Stok')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Manajemen Stok</h1>
        <p class="text-sm text-stone-500 mt-1">Semua perubahan stok tercatat di riwayat.</p>
    </div>
    <a href="{{ route('admin.stock.history') }}"
       class="border border-stone-300 hover:bg-stone-50 px-5 py-2.5 rounded-lg font-medium transition">
        Riwayat Stok
    </a>
</div>

<div class="grid grid-cols-3 gap-3 mb-6">
    <a href="{{ route('admin.stock.index') }}" class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Total Produk</div>
        <div class="text-2xl font-bold mt-1">{{ $stats['total'] }}</div>
    </a>
    <a href="{{ route('admin.stock.index', ['status' => 'low']) }}" class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Stok Menipis</div>
        <div class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['low'] }}</div>
    </a>
    <a href="{{ route('admin.stock.index', ['status' => 'out']) }}" class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Stok Habis</div>
        <div class="text-2xl font-bold text-red-600 mt-1">{{ $stats['out'] }}</div>
    </a>
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / SKU"
           class="flex-1 min-w-[200px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="category_id" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Kategori</option>
        @foreach($categories as $c)
            <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>
        @endforeach
    </select>
    <select name="status" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Status</option>
        <option value="out" @selected(request('status') === 'out')>Habis</option>
        <option value="low" @selected(request('status') === 'low')>Menipis</option>
        <option value="ok" @selected(request('status') === 'ok')>Aman</option>
    </select>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-3">Produk</th>
                <th class="px-4 py-3">SKU</th>
                <th class="px-4 py-3 text-right">Stok</th>
                <th class="px-4 py-3 text-right">Batas</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 w-72">Sesuaikan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($products as $p)
            @php
                $state = $p->stock <= 0 ? 'out' : ($p->stock <= $p->stock_threshold ? 'low' : 'ok');
            @endphp
            <tr>
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $p->name }}</div>
                    <div class="text-xs text-stone-500">{{ $p->category->name ?? '-' }} · {{ $p->brand->name ?? '-' }}</div>
                </td>
                <td class="px-4 py-3 font-mono text-xs">{{ $p->sku }}</td>
                <td class="px-4 py-3 text-right font-bold">{{ $p->stock }}</td>
                <td class="px-4 py-3 text-right text-stone-500">{{ $p->stock_threshold }}</td>
                <td class="px-4 py-3">
                    @if($state === 'out')
                        <span class="px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs">Habis</span>
                    @elseif($state === 'low')
                        <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 text-xs">Menipis</span>
                    @else
                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-700 text-xs">Aman</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @can('manage_stock')
                    <form method="POST" action="{{ route('admin.stock.adjust') }}" class="flex flex-wrap gap-1.5">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $p->id }}">
                        <select name="type" class="border border-stone-300 rounded px-2 py-1 text-xs">
                            <option value="in">Masuk (+)</option>
                            <option value="out">Keluar (−)</option>
                            <option value="set">Opname (=)</option>
                        </select>
                        <input type="number" name="quantity" min="0" required placeholder="Jml"
                               class="w-16 border border-stone-300 rounded px-2 py-1 text-xs">
                        <input type="text" name="note" maxlength="255" placeholder="Alasan"
                               class="w-28 border border-stone-300 rounded px-2 py-1 text-xs">
                        <button class="bg-stone-900 text-white px-3 py-1 rounded text-xs">Simpan</button>
                    </form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Tidak ada produk.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>

@endsection
