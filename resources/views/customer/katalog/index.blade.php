@extends('layouts.app')

@section('title', 'Katalog Produk')

@section('content')
<x-page-header page="katalog" title="Katalog Produk" :subtitle="$site['tagline']" :crumbs="[['Beranda', route('home')], ['Katalog Produk']]" />

<div class="container-x grid gap-10 py-10 lg:grid-cols-[240px_1fr]" x-data="{ filters: false }">

    {{-- Filter --}}
    <aside class="lg:sticky lg:top-24 lg:self-start">
        <button type="button" @click="filters = !filters" class="btn btn-outline btn-sm mb-4 w-full lg:hidden">
            <span x-text="filters ? 'Tutup Filter' : 'Tampilkan Filter'"></span>
        </button>

        <form id="filter-form" method="GET" action="{{ route('products.index') }}"
              class="space-y-5 rounded-xl border border-stone-200 bg-white p-5 lg:block"
              :class="filters ? 'block' : 'hidden lg:block'">
            <div class="flex items-center justify-between">
                <b class="text-sm">Filter</b>
                <a href="{{ route('products.index') }}" class="text-[13px] text-orange-600 hover:underline">Reset</a>
            </div>

            <div>
                <label for="f-search" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-500">Cari</label>
                <input id="f-search" type="text" name="search" value="{{ request('search') }}" placeholder="Nama produk…" class="field">
            </div>

            <div>
                <label for="f-cat" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-500">Kategori</label>
                <select id="f-cat" name="category" class="field">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->slug }}" @selected(request('category') == $cat->slug)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="f-brand" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-500">Merek</label>
                <select id="f-brand" name="brand" class="field">
                    <option value="">Semua Merek</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->slug }}" @selected(request('brand') == $brand->slug)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-full">Terapkan Filter</button>
        </form>
    </aside>

    {{-- Hasil --}}
    <div class="min-w-0">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm text-stone-500">{{ $products->total() }} produk</span>
            <div class="flex items-center gap-2 text-sm">
                <label for="f-sort" class="text-stone-500">Urutkan</label>
                <select id="f-sort" name="sort" form="filter-form" onchange="this.form.submit()" class="field !h-10 !w-auto pr-8">
                    <option value="latest" @selected(request('sort', 'latest') == 'latest')>Terbaru</option>
                    <option value="price_asc" @selected(request('sort') == 'price_asc')>Harga Terendah</option>
                    <option value="price_desc" @selected(request('sort') == 'price_desc')>Harga Tertinggi</option>
                    <option value="name_asc" @selected(request('sort') == 'name_asc')>Nama A–Z</option>
                </select>
            </div>
        </div>

        @if($products->count())
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-3 lg:gap-5">
                @foreach($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-10">{{ $products->links() }}</div>
        @else
            <div class="rounded-xl border border-stone-200 bg-white py-16 text-center">
                <p class="text-stone-500">Produk tidak ditemukan.</p>
                <a href="{{ route('products.index') }}" class="mt-3 inline-block text-sm text-orange-600 hover:underline">Reset filter</a>
            </div>
        @endif
    </div>
</div>
@endsection
