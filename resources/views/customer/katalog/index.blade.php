@extends('layouts.shop')

@section('title', isset($currentCategory) && $currentCategory ? $currentCategory->name : 'Katalog Produk')

@section('content')
@php
    $currentCategory = $currentCategory ?? null; // aman walau controller belum diperbarui
    $subCategories = $subCategories ?? collect();
@endphp

@if($landing)
    {{-- HERO: foto besar polos (Admin > Pengaturan > Tampilan > hero_bg), tanpa tulisan/kartu di atasnya --}}
    <section class="relative isolate flex min-h-[320px] flex-col overflow-hidden bg-[#1a0f0a] text-white sm:min-h-[460px] lg:min-h-[70vh]">
        @if(!empty($site['hero_bg']))
            <img src="{{ media_url($site['hero_bg']) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover" fetchpriority="high">
            <div class="absolute inset-0 -z-10 bg-[#1a0f0a]/20"></div>
            <div class="absolute inset-x-0 bottom-0 -z-10 h-1/4 bg-gradient-to-t from-[#1a0f0a]/50 to-transparent"></div>
        @else
            <div class="absolute inset-0 -z-20 bg-gradient-to-br from-[#24150e] via-[#35200f] to-brand"></div>
            <div class="auth-beans absolute inset-0 -z-10"></div>
            <div class="absolute -right-24 top-1/4 -z-10 h-[28rem] w-[28rem] rounded-full bg-orange-500/20 blur-3xl"></div>
        @endif
        {{-- Judul disembunyikan dari tampilan (tetap dibaca mesin pencari & pembaca layar) --}}
        <h1 class="sr-only">Toko {{ $site['site_name'] }}: lengkapi kedai kopi Anda dari satu toko.</h1>
    </section>

    {{-- KATEGORI: baris ikon ringkas (gaya Toffin) tepat di bawah hero --}}
    @if($categoryCards->isNotEmpty())
        <section class="container-x pt-10" aria-label="Kategori">
            <div class="flex flex-wrap justify-center gap-x-4 gap-y-6 sm:gap-x-7">
                @foreach($categoryCards as $c)
                    @php $cardImg = $c->card_image ?? $c->image ?? null; @endphp
                    <a href="{{ route('products.index', ['category' => $c->slug]) }}" class="group w-[84px] text-center sm:w-[112px]">
                        <div class="relative isolate mx-auto flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-white p-2.5 ring-1 ring-stone-200 transition group-hover:shadow-md group-hover:ring-orange-400">
                            @if($cardImg)
                                <img src="{{ media_url($cardImg) }}" alt="" loading="lazy" class="h-full w-full object-contain transition duration-300 group-hover:scale-105">
                            @else
                                <div class="absolute inset-0 -z-10 bg-gradient-to-br from-[#35200f] to-[#1a0f0a]"></div>
                                <div class="auth-beans absolute inset-0 -z-10"></div>
                            @endif
                        </div>
                        <span class="mt-2 block text-[13px] font-medium leading-tight text-stone-800 transition group-hover:text-orange-600">{{ $c->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- SOROTAN (slider produk unggulan) --}}
    @if($featured->isNotEmpty())
        <section class="container-x pb-16 pt-14 sm:pb-20" aria-label="Produk unggulan"
                 x-data="{ i: 0, n: {{ $featured->count() }}, timer: null,
                           next() { this.i = (this.i + 1) % this.n },
                           play() { if (this.n > 1) this.timer = setInterval(() => this.next(), 6000) },
                           stop() { clearInterval(this.timer) } }"
                 x-init="play()" @mouseenter="stop()" @mouseleave="play()">
            <div class="relative grid rounded-3xl border border-stone-200 bg-white p-5 sm:p-6 md:py-10 md:pl-8 md:pr-14">
                @foreach($featured as $spot)
                    @php $spotImg = $spot->images->first(); @endphp
                    <div class="col-start-1 row-start-1 grid items-center gap-6 transition-opacity duration-500 md:grid-cols-[minmax(0,300px)_1fr] md:gap-12"
                         :class="i === {{ $loop->index }} ? 'opacity-100' : 'pointer-events-none opacity-0'"
                         :aria-hidden="i !== {{ $loop->index }}">
                        <a href="{{ route('products.show', $spot->slug) }}" class="block aspect-[4/5] overflow-hidden rounded-2xl bg-stone-100 shadow-xl shadow-stone-900/15 md:-my-16 lg:-ml-10" :tabindex="i === {{ $loop->index }} ? 0 : -1">
                            @if($spotImg)
                                <img src="{{ media_url($spotImg->image) }}" alt="{{ $spot->name }}" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </a>
                        <div>
                            <span class="eyebrow">Unggulan</span>
                            <h2 class="heading mt-2 text-2xl leading-tight sm:text-3xl">{{ $spot->name }}</h2>
                            <p class="mt-3 max-w-md text-[15px] leading-relaxed text-stone-600">{{ \Illuminate\Support\Str::limit(strip_tags((string) $spot->description), 170) }}</p>
                            <div class="mt-4 text-xl font-semibold text-orange-500">Rp {{ number_format($spot->price, 0, ',', '.') }}</div>
                            <a href="{{ route('products.show', $spot->slug) }}" class="btn btn-primary mt-5 !h-12 !rounded-full px-7" :tabindex="i === {{ $loop->index }} ? 0 : -1">Jelajahi sekarang</a>
                        </div>
                    </div>
                @endforeach

                @if($featured->count() > 1)
                    <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 flex-row gap-2 md:bottom-auto md:left-auto md:right-5 md:top-1/2 md:-translate-y-1/2 md:translate-x-0 md:flex-col">
                        @foreach($featured as $spot)
                            <button type="button" @click="i = {{ $loop->index }}" class="h-2 w-2 rounded-full transition" :class="i === {{ $loop->index }} ? 'bg-orange-400' : 'bg-stone-300 hover:bg-stone-400'" aria-label="Tampilkan unggulan {{ $loop->iteration }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- KELEBIHAN (edit teks di array $perks sesuai kebutuhan) --}}
    <section class="container-x {{ $featured->isEmpty() ? 'pt-14' : '' }} pb-4" aria-labelledby="plus-title">
        <h2 id="plus-title" class="heading mb-6 text-2xl sm:text-3xl">Kelebihan berbelanja di <span class="text-orange-500">{{ $site['site_name'] }}</span></h2>
        @php
            $perks = [
                ['Rangkaian produk lengkap untuk kedai dan roastery Anda.', 'M3 9l1.5-5h15L21 9M4 9v11h16V9M9 20v-6h6v6'],
                ['Lacak pengiriman pesanan langsung dari akun Anda.', 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3z'],
                ['Ongkir dihitung otomatis sesuai alamat tujuan.', 'M3 7h18v12H3zM3 7l2-3h14l2 3M16 13h2'],
                ['Ajukan retur langsung dari akun, tanpa ribet.', 'M4 12a8 8 0 018-8 8 8 0 016 2.6M20 4v4h-4M20 12a8 8 0 01-8 8 8 8 0 01-6-2.6M4 20v-4h4'],
                ['Jaminan garansi perawatan dan perbaikan suku cadang.', 'M12 15a6 6 0 100-12 6 6 0 000 12zM8.5 14l-1.5 7 5-3 5 3-1.5-7'],
                ['Tersedia paket bundle yang lebih hemat dan memudahkan Anda berbelanja.', 'M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8'],
                ['Konsultasi gratis untuk mendapatkan produk yang tepat untuk bisnis Anda.', 'M4 14v-2a8 8 0 0116 0v2M4 14h3v5H5a1 1 0 01-1-1zM20 14h-3v5h2a1 1 0 001-1zM17 19c0 1.5-2 2-5 2'],
            ];
        @endphp
        <x-rail>
            @foreach($perks as [$text, $icon])
                <div class="w-[240px] shrink-0 snap-start rounded-2xl bg-gradient-to-br from-orange-400 to-orange-600 p-5 text-white sm:w-[calc(25%-12px)]">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                    <p class="mt-4 text-[15px] font-medium leading-snug">{{ $text }}</p>
                </div>
            @endforeach
        </x-rail>
    </section>
@else
    @if($currentCategory)
        {{-- BANNER KATEGORI (gaya Toffin): nama kategori di atas foto --}}
        <section class="container-x pt-6 sm:pt-8">
            <nav class="mb-3 text-[13px] text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('products.index') }}" class="hover:text-orange-600">Toko</a>
                @if($currentCategory->parent)
                    <span class="mx-1.5">/</span><a href="{{ route('products.index', ['category' => $currentCategory->parent->slug]) }}" class="hover:text-orange-600">{{ $currentCategory->parent->name }}</a>
                @endif
                <span class="mx-1.5">/</span><span class="text-stone-800">{{ $currentCategory->name }}</span>
            </nav>
            <div class="relative isolate flex h-40 items-center justify-center overflow-hidden rounded-2xl bg-[#26180f] sm:h-60 lg:h-72">
                @if($currentCategory->image)
                    <img src="{{ media_url($currentCategory->image) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover">
                    <div class="absolute inset-0 -z-10 bg-black/45"></div>
                @else
                    <div class="absolute inset-0 -z-20 bg-gradient-to-br from-[#24150e] via-[#35200f] to-brand"></div>
                    <div class="auth-beans absolute inset-0 -z-10"></div>
                @endif
                <h1 class="heading px-4 text-center text-3xl uppercase tracking-[0.14em] text-white sm:text-5xl">{{ $currentCategory->name }}</h1>
            </div>
        </section>
    @else
        @php $brandName = $brands->firstWhere('slug', request('brand'))->name ?? null; @endphp
        <div class="border-b border-stone-200 bg-white">
            <div class="container-x py-6">
                <h1 class="heading text-2xl sm:text-[1.9rem]">{{ request('search') ? 'Hasil untuk “' . request('search') . '”' : ($brandName ? 'Merek ' . $brandName : 'Katalog Produk') }}</h1>
                <p class="mt-1 text-sm text-stone-600">{{ $site['tagline'] }}</p>
            </div>
        </div>
    @endif
@endif

{{-- DAFTAR PRODUK --}}
<div id="semua" class="container-x {{ $landing ? 'py-10' : 'pb-12 pt-6' }}"
     x-data="{ filters: {{ request()->hasAny(['brand', 'min_price', 'max_price']) || request()->boolean('in_stock') ? 'true' : 'false' }} }">

    @unless($landing)
        {{-- Kartu filter: chip sub-kategori + cari + tombol Filter --}}
        <form id="filter-form" method="GET" action="{{ route('products.index') }}" class="mb-8 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:p-5">
            @if(request('category'))
                <input type="hidden" name="category" value="{{ request('category') }}">
            @endif

            @if($currentCategory && $subCategories->isNotEmpty())
                @php $root = $currentCategory->parent ?? $currentCategory; @endphp
                <div class="flex flex-wrap gap-2 border-b border-stone-200 pb-4 text-[13px]">
                    <a href="{{ route('products.index', ['category' => $root->slug]) }}"
                       class="rounded-full border px-4 py-1.5 transition {{ $currentCategory->id === $root->id ? 'border-brand bg-brand text-white' : 'border-stone-300 bg-white text-stone-700 hover:border-orange-500' }}">Semua</a>
                    @foreach($subCategories as $sc)
                        <a href="{{ route('products.index', ['category' => $sc->slug]) }}"
                           class="rounded-full border px-4 py-1.5 transition {{ $currentCategory->id === $sc->id ? 'border-brand bg-brand text-white' : 'border-stone-300 bg-white text-stone-700 hover:border-orange-500' }}">{{ $sc->name }}</a>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 {{ $currentCategory && $subCategories->isNotEmpty() ? 'pt-4' : '' }}">
                <input id="f-search" type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, SKU, deskripsi…" class="field !h-10 min-w-[180px] flex-1" aria-label="Cari produk">
                <button type="submit" class="btn btn-primary btn-sm !h-10 !rounded-full px-5">Cari</button>
                <button type="button" @click="filters = !filters" class="btn btn-outline btn-sm !h-10 !rounded-full px-5" :aria-expanded="filters">Filter</button>
                <select id="f-sort" name="sort" onchange="this.form.submit()" class="field !h-10 !w-auto pr-8" aria-label="Urutkan">
                    <option value="latest" @selected(request('sort', 'latest') == 'latest')>Terbaru</option>
                    <option value="price_asc" @selected(request('sort') == 'price_asc')>Harga Terendah</option>
                    <option value="price_desc" @selected(request('sort') == 'price_desc')>Harga Tertinggi</option>
                    <option value="name_asc" @selected(request('sort') == 'name_asc')>Nama A–Z</option>
                </select>
            </div>

            <div x-show="filters" x-cloak x-transition class="mt-4 grid gap-4 border-t border-stone-200 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="f-brand" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-500">Merek</label>
                    <select id="f-brand" name="brand" class="field">
                        <option value="">Semua Merek</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->slug }}" @selected(request('brand') == $brand->slug)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-500">Harga (Rp)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_price" min="0" value="{{ request('min_price') }}" placeholder="Min" class="field" aria-label="Harga minimum">
                        <span class="text-stone-400">–</span>
                        <input type="number" name="max_price" min="0" value="{{ request('max_price') }}" placeholder="Maks" class="field" aria-label="Harga maksimum">
                    </div>
                </div>
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-stone-700">
                    <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock')) class="rounded border-stone-300 text-orange-600 focus:ring-orange-500">
                    Hanya yang stoknya tersedia
                </label>
                <div class="flex items-end gap-3">
                    <button type="submit" class="btn btn-primary w-full">Terapkan</button>
                    <a href="{{ request('category') ? route('products.index', ['category' => request('category')]) : route('products.index') }}" class="text-[13px] text-orange-600 hover:underline">Reset</a>
                </div>
            </div>
        </form>
    @endunless

    @if($landing)
        <div class="mb-6 flex items-end justify-between gap-4">
            <h2 class="heading text-2xl sm:text-3xl">Semua produk</h2>
            <span class="text-sm text-stone-500">{{ $products->total() }} produk</span>
        </div>
    @else
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <span class="mr-2 text-sm text-stone-500">{{ $products->total() }} produk</span>
            @php
                $chips = array_filter([
                    'brand'     => $brands->firstWhere('slug', request('brand'))->name ?? null,
                    'search'    => request('search') ? '“' . request('search') . '”' : null,
                    'min_price' => request('min_price') ? 'Min Rp ' . number_format((int) request('min_price'), 0, ',', '.') : null,
                    'max_price' => request('max_price') ? 'Maks Rp ' . number_format((int) request('max_price'), 0, ',', '.') : null,
                    'in_stock'  => request()->boolean('in_stock') ? 'Stok tersedia' : null,
                ]);
            @endphp
            @foreach($chips as $key => $label)
                <a href="{{ route('products.index', request()->except($key, 'page')) }}" class="inline-flex items-center gap-1.5 rounded-full border border-stone-300 bg-white px-3 py-1 text-xs text-stone-700 transition hover:border-orange-500">
                    {{ $label }} <span aria-hidden="true">×</span>
                </a>
            @endforeach
        </div>
    @endif

    @if($products->count())
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 lg:gap-5">
            @foreach($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @else
        <div class="rounded-xl border border-stone-200 bg-white py-16 text-center">
            <p class="text-stone-500">Produk tidak ditemukan.</p>
            <a href="{{ request('category') ? route('products.index', ['category' => request('category')]) : route('products.index') }}" class="mt-3 inline-block text-sm text-orange-600 hover:underline">Reset filter</a>
        </div>
    @endif
</div>
@endsection
