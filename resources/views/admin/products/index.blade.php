@extends('layouts.admin')

@section('title', 'Produk')

@section('content')

{{-- Header --}}
<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Katalog Produk</h1>
        <p class="text-sm text-stone-500 mt-1">Kelola produk, stok, dan status tayang.</p>
    </div>
    <a href="{{ route('admin.products.create') }}"
       class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium transition flex items-center gap-2">
        <span>+</span> Tambah Produk
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Total Produk</div>
        <div class="text-2xl font-bold text-stone-900 mt-1">{{ $stats['total'] }}</div>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Aktif</div>
        <div class="text-2xl font-bold text-green-600 mt-1">{{ $stats['active'] }}</div>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Stok Menipis</div>
        <div class="text-2xl font-bold text-orange-500 mt-1">{{ $stats['low_stock'] }}</div>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Stok Habis</div>
        <div class="text-2xl font-bold text-red-600 mt-1">{{ $stats['out_of_stock'] }}</div>
    </div>
</div>

{{-- Filter --}}
<div class="bg-white p-4 rounded-lg border border-stone-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Cari nama / SKU..."
               class="lg:col-span-2 border border-stone-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500">

        <select name="category" class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Semua Kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>

        <select name="brand" class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Semua Merek</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}" {{ request('brand') == $brand->id ? 'selected' : '' }}>
                    {{ $brand->name }}
                </option>
            @endforeach
        </select>

        <select name="status" class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Nonaktif</option>
            <option value="low_stock" {{ request('status') == 'low_stock' ? 'selected' : '' }}>Stok Menipis</option>
        </select>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-stone-800 hover:bg-stone-900 text-white px-4 py-2 rounded-lg text-sm font-medium">
                Filter
            </button>
            <a href="{{ route('admin.products.index') }}"
               class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-50">
                Reset
            </a>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="bg-white rounded-lg border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-stone-600 w-16">Gambar</th>
                    <th class="px-4 py-3 text-left font-medium text-stone-600">Produk</th>
                    <th class="px-4 py-3 text-left font-medium text-stone-600">Kategori</th>
                    <th class="px-4 py-3 text-right font-medium text-stone-600">Harga</th>
                    <th class="px-4 py-3 text-center font-medium text-stone-600">Stok</th>
                    <th class="px-4 py-3 text-center font-medium text-stone-600">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-stone-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse($products as $product)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3">
                            <div class="w-12 h-12 rounded bg-stone-100 overflow-hidden">
                                @if($product->images->first())
                                    <img src="{{ asset('storage/' . $product->images->first()->image) }}"
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-stone-400 text-xs">
                                        N/A
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-stone-900">{{ $product->name }}</div>
                            <div class="text-xs text-stone-500 mt-0.5">
                                SKU: {{ $product->sku }}
                                @if($product->is_featured)
                                    <span class="ml-2 text-orange-500">★ Featured</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-stone-600">
                            {{ $product->category->name ?? '-' }}
                            @if($product->brand)
                                <div class="text-xs text-stone-400">{{ $product->brand->name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium">
                            Rp {{ number_format($product->price, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($product->stock == 0)
                                <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800 font-medium">
                                    Habis
                                </span>
                            @elseif($product->stock <= $product->stock_threshold)
                                <span class="text-xs px-2 py-1 rounded-full bg-orange-100 text-orange-800 font-medium">
                                    {{ $product->stock }} (menipis)
                                </span>
                            @else
                                <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800 font-medium">
                                    {{ $product->stock }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <form method="POST" action="{{ route('admin.products.toggle-active', $product->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="text-xs px-2 py-1 rounded-full font-medium transition
                                               {{ $product->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}">
                                    {{ $product->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.products.edit', $product->id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}"
                                      onsubmit="return confirm('Yakin hapus produk {{ $product->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-stone-500">
                            <div class="text-4xl mb-3">📦</div>
                            <p class="mb-3">Belum ada produk.</p>
                            <a href="{{ route('admin.products.create') }}"
                               class="inline-block bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-lg text-sm font-medium">
                                + Tambah Produk Pertama
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pagination --}}
<div class="mt-4">
    {{ $products->links() }}
</div>

@endsection