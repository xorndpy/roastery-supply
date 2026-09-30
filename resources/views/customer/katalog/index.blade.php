@extends('layouts.app')

@section('title', 'Katalog Produk')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <h1 class="text-4xl font-bold mb-2">Katalog Produk</h1>
    <p class="text-stone-600 mb-8">Mesin kopi profesional untuk kedai kopi serius.</p>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        
        {{-- Filter Sidebar --}}
        <aside class="lg:col-span-1">
            <form method="GET" action="{{ route('products.index') }}" class="space-y-6">
                
                {{-- Search --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Cari Produk</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500"
                           placeholder="Nama produk...">
                </div>

                {{-- Kategori --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Kategori</label>
                    <select name="category" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}" {{ request('category') == $cat->slug ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Merek --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Merek</label>
                    <select name="brand" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Semua Merek</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->slug }}" {{ request('brand') == $brand->slug ? 'selected' : '' }}>
                                {{ $brand->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Sort --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Urutkan</label>
                    <select name="sort" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                        <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>Terbaru</option>
                        <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Harga Terendah</option>
                        <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Harga Tertinggi</option>
                        <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>Nama A-Z</option>
                    </select>
                </div>

                <button type="submit" 
                        class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2 rounded-lg font-medium transition">
                    Terapkan Filter
                </button>
                <a href="{{ route('products.index') }}" 
                   class="block text-center text-sm text-stone-500 hover:text-stone-700">
                    Reset
                </a>
            </form>
        </aside>

        {{-- Product Grid --}}
        <div class="lg:col-span-3">
            @if($products->count())
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($products as $product)
                        <a href="{{ route('products.show', $product->slug) }}"
                           class="bg-white rounded-lg overflow-hidden border border-stone-200 hover:shadow-lg transition group">
                            <div class="aspect-square bg-stone-100 overflow-hidden">
                                @if($product->images->first())
                                    <img src="{{ asset('storage/' . $product->images->first()->image) }}"
                                         alt="{{ $product->name }}"
                                         class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-stone-400 text-sm">
                                        No Image
                                    </div>
                                @endif
                            </div>
                            <div class="p-4">
                                <div class="text-xs text-stone-500 uppercase">{{ $product->brand->name ?? '' }}</div>
                                <div class="font-medium text-stone-900 mt-1 line-clamp-2">{{ $product->name }}</div>
                                <div class="text-orange-500 font-bold mt-2">
                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @else
                <div class="text-center py-16 bg-white rounded-lg border border-stone-200">
                    <p class="text-stone-500">Belum ada produk.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection