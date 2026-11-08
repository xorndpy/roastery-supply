@extends('layouts.shop')

@section('title', 'Katalog Produk')

@section('content')

@if($landing)
    @php $multiPromo = $promos->count() > 1; @endphp

    {{-- HERO: polos & besar. Bila ada banner "Katalog" (Admin > Banner), hero ini menjadi slider foto promo/terbaru. --}}
    @if($promos->isNotEmpty())
        <section class="relative isolate overflow-hidden bg-[#26180f]" aria-label="Promo"
                 @if($multiPromo)
                     x-data="{ i: 0, n: {{ $promos->count() }}, timer: null,
                               play() { if (!matchMedia('(prefers-reduced-motion: reduce)').matches) this.timer = setInterval(() => { this.i = (this.i + 1) % this.n }, 6000) },
                               stop() { clearInterval(this.timer) } }"
                     x-init="play()" @mouseenter="stop()" @mouseleave="stop(); play()"
                 @endif>
            <h1 class="sr-only">Katalog produk {{ $site['site_name'] }}</h1>
            <div class="relative aspect-[16/9] max-h-[560px] sm:aspect-[2/1] lg:aspect-[21/8]">
                @foreach($promos as $k => $promo)
                    @php
                        $promoImg = '<img src="' . e(media_url($promo->image)) . '" alt="' . e($promo->title) . '" class="h-full w-full object-cover"' . ($k === 0 ? ' fetchpriority="high"' : ' loading="lazy"') . '>';
                    @endphp
                    <div class="absolute inset-0 transition-opacity duration-700 {{ $k === 0 ? '' : 'pointer-events-none opacity-0' }}"
                         @if($multiPromo)
                             @if($k === 0) :class="{ 'pointer-events-none opacity-0': i !== 0 }" @else :class="{ 'pointer-events-auto opacity-100': i === {{ $k }} }" @endif
                             :aria-hidden="i !== {{ $k }}"
                         @endif>
                        @if($promo->link)
                            <a href="{{ $promo->link }}" class="block h-full w-full focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orange-500" @if($multiPromo) :tabindex="i === {{ $k }} ? 0 : -1" @endif>{!! $promoImg !!}</a>
                        @else
                            {!! $promoImg !!}
                        @endif
                    </div>
                @endforeach
            </div>

            @if($multiPromo)
                <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-2 rounded-full bg-black/30 px-3 py-2 backdrop-blur-sm" role="group" aria-label="Pilih promo">
                    @foreach($promos as $k => $promo)
                        <button type="button" @click="i = {{ $k }}" class="h-1.5 rounded-full transition-all duration-300"
                                :class="i === {{ $k }} ? 'w-6 bg-white' : 'w-1.5 bg-white/60 hover:bg-white'"
                                :aria-current="i === {{ $k }} ? 'true' : 'false'" aria-label="Promo {{ $loop->iteration }} dari {{ $promos->count() }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <section class="relative overflow-hidden bg-[#26180f] text-white">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-orange-500/25 blur-3xl"></div>
            <div class="container-x relative flex min-h-[300px] flex-col justify-center py-14 sm:min-h-[380px] lg:min-h-[440px]">
                <p class="eyebrow !text-orange-300">Toko {{ $site['site_name'] }}</p>
                <h1 class="heading mt-3 max-w-3xl text-[2.25rem] leading-[1.08] [text-wrap:balance] sm:text-6xl">
                    <span class="text-orange-300">Eksplor.</span> Lengkapi kedai kopi Anda dari satu toko.
                </h1>
                <p class="mt-5 max-w-xl text-base text-stone-300">{{ $site['tagline'] }}</p>
            </div>
        </section>
    @endif

    {{-- KATEGORI (strip bersih: foto tanpa kotak, nama di bawahnya) --}}
    @if($categoryCards->isNotEmpty())
        <section class="container-x pt-10 sm:pt-12" aria-label="Kategori produk">
            <x-rail>
                @foreach($categoryCards as $c)
                    <a href="{{ route('products.index', ['category' => $c->slug]) }}" class="group flex w-[104px] shrink-0 snap-start flex-col items-center text-center first:ml-auto last:mr-auto sm:w-[132px]">
                        @if(!empty($c->image))
                            <img src="{{ media_url($c->image) }}" alt="" loading="lazy" class="h-16 w-16 object-contain transition duration-300 group-hover:scale-110 sm:h-20 sm:w-20">
                        @else
                            <span class="heading flex h-16 w-16 items-center justify-center rounded-full bg-orange-50 text-2xl text-orange-600 sm:h-20 sm:w-20">{{ \Illuminate\Support\Str::substr($c->name, 0, 1) }}</span>
                        @endif
                        <span class="mt-3 text-[13px] font-semibold text-orange-600 group-hover:text-orange-700">{{ $c->name }}</span>
                    </a>
                @endforeach
            </x-rail>
        </section>
    @endif

    {{-- TERBARU (selalu di atas semua produk lain) --}}
    @if($latest->isNotEmpty())
        <section class="container-x pt-14 sm:pt-16" aria-labelledby="terbaru-title">
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 id="terbaru-title" class="heading text-2xl sm:text-3xl"><span class="text-orange-500">Terbaru.</span> Temukan sekarang.</h2>
                <a href="#semua" class="hidden text-sm font-medium text-orange-700 hover:underline sm:block">Lihat semua →</a>
            </div>
            <x-rail>
                @foreach($latest as $product)
                    <div class="w-[176px] shrink-0 snap-start sm:w-[230px] lg:w-[250px]"><x-product-card :product="$product" /></div>
                @endforeach
            </x-rail>
        </section>
    @endif

    {{-- SOROTAN (slider produk unggulan) --}}
    @if($featured->isNotEmpty())
        <section class="container-x py-16 sm:py-20" aria-label="Produk unggulan"
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
                <div class="w-[240px] shrink-0 snap-start rounded-2xl border border-stone-200 bg-white p-5 text-stone-800 sm:w-[calc(25%-12px)]">
                    <svg class="h-8 w-8 text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                    <p class="mt-4 text-[15px] font-medium leading-snug">{{ $text }}</p>
                </div>
            @endforeach
        </x-rail>
    </section>
@else
    @php
        $curSlug = request('category');
        $current = $curSlug
            ? ($categories->firstWhere('slug', $curSlug) ?? $categories->flatMap(fn ($c) => $c->children)->firstWhere('slug', $curSlug))
            : null;
        $parentCat = $current ? ($current->parent_id ? $categories->firstWhere('id', $current->parent_id) : $current) : null;
        $pillCats = $parentCat ? $parentCat->children : collect();
    @endphp

    @if($current)
        {{-- Banner kategori: foto kategori -> foto header umum -> latar polos --}}
        @php $bannerPhoto = $current->banner ?: ($site['header_bg'] ?? null); @endphp
        <section class="container-x pt-6" aria-label="Kategori {{ $current->name }}">
            <div class="relative isolate flex h-[180px] items-center justify-center overflow-hidden rounded-2xl bg-[#26180f] text-center text-white sm:h-[240px] lg:h-[280px]">
                @if($bannerPhoto)
                    <img src="{{ media_url($bannerPhoto) }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover">
                    <div class="absolute inset-0 -z-10 bg-black/40"></div>
                @else
                    <div class="pointer-events-none absolute -right-16 -top-16 -z-10 h-72 w-72 rounded-full bg-orange-500/25 blur-3xl"></div>
                @endif
                <div class="px-6">
                    <h1 class="heading text-3xl uppercase tracking-[0.08em] sm:text-5xl">{{ $current->name }}</h1>
                    @if($current->description)
                        <p class="mx-auto mt-3 hidden max-w-xl text-sm text-stone-200 sm:block">{{ \Illuminate\Support\Str::limit(strip_tags((string) $current->description), 120) }}</p>
                    @endif
                </div>
            </div>
        </section>
    @else
        <div class="border-b border-stone-200 bg-white">
            <div class="container-x py-6">
                <h1 class="heading text-2xl sm:text-[1.9rem]">{{ request('search') ? 'Hasil untuk “' . request('search') . '”' : 'Katalog Produk' }}</h1>
                <p class="mt-1 text-sm text-stone-600">{{ $site['tagline'] }}</p>
            </div>
        </div>
    @endif
@endif

{{-- SEMUA PRODUK --}}
<div id="semua" class="container-x {{ $landing ? 'pb-12 pt-16 sm:pt-20' : 'pb-12 pt-5 sm:pt-6' }}">

    @unless($landing)
    @php
        // Pill: sub-kategori dari kategori aktif; bila tidak ada, kategori utama.
        $pills = $pillCats->isNotEmpty() ? $pillCats : $categories;
        $allLabel = $pillCats->isNotEmpty() ? 'Semua ' . $parentCat->name : 'Semua';
        $allSlug = $pillCats->isNotEmpty() ? $parentCat->slug : null;
        $allUrl = route('products.index', array_merge(request()->except('page', 'category'), $allSlug ? ['category' => $allSlug] : []));
        $allActive = $allSlug ? ($current && $current->id === $parentCat->id) : !request('category');
        $filterKeys = ['brand', 'min_price', 'max_price', 'in_stock'];
        $activeCount = (request('brand') ? 1 : 0) + ((request('min_price') || request('max_price')) ? 1 : 0) + (request()->boolean('in_stock') ? 1 : 0);
        $resetUrl = route('products.index', request()->except(array_merge($filterKeys, ['page'])));
    @endphp

    <div x-data="filterDrawer" @keydown.escape.window="filters && close()">

        {{-- Toolbar: pill kategori, cari, urutkan, tombol Filter --}}
        <form id="filter-form" method="GET" action="{{ route('products.index') }}" class="rounded-2xl border border-stone-200 bg-white">
            @if(request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif

            <nav aria-label="Kategori" class="px-3 pt-3 sm:px-4 sm:pt-4">
                <div class="-mx-3 flex gap-2 overflow-x-auto px-3 pb-3 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-4 [&::-webkit-scrollbar]:hidden">
                    <a href="{{ $allUrl }}" @if($allActive) aria-current="page" @endif
                       class="inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm transition {{ $allActive ? 'border-brand bg-brand text-white' : 'border-stone-300 text-stone-700 hover:border-stone-500' }}">{{ $allLabel }}</a>
                    @foreach($pills as $pill)
                        @php $pillActive = request('category') === $pill->slug; @endphp
                        <a href="{{ route('products.index', array_merge(request()->except('page'), ['category' => $pill->slug])) }}" @if($pillActive) aria-current="page" @endif
                           class="inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm transition {{ $pillActive ? 'border-brand bg-brand text-white' : 'border-stone-300 text-stone-700 hover:border-stone-500' }}">{{ $pill->name }}</a>
                    @endforeach
                </div>
            </nav>

            <div class="flex flex-wrap items-center gap-2 border-t border-stone-100 p-3 sm:gap-3 sm:p-4">
                <div class="relative w-full sm:max-w-sm sm:flex-1">
                    <label for="f-search" class="sr-only">Cari produk</label>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>
                    <input id="f-search" type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama, SKU, deskripsi…" enterkeyhint="search" class="field !pl-9">
                </div>

                <div class="flex w-full items-center gap-2 sm:ml-auto sm:w-auto">
                    <label for="f-sort" class="sr-only">Urutkan</label>
                    <select id="f-sort" name="sort" onchange="this.form.submit()" class="field min-w-0 flex-1 sm:w-auto sm:flex-none sm:pr-9">
                        <option value="latest" @selected(request('sort', 'latest') == 'latest')>Terbaru</option>
                        <option value="price_asc" @selected(request('sort') == 'price_asc')>Harga Terendah</option>
                        <option value="price_desc" @selected(request('sort') == 'price_desc')>Harga Tertinggi</option>
                        <option value="name_asc" @selected(request('sort') == 'name_asc')>Nama A–Z</option>
                    </select>
                    <button type="button" x-ref="trigger" @click="open()" aria-haspopup="dialog" :aria-expanded="filters.toString()" class="btn btn-outline shrink-0 gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></svg>
                        Filter
                        @if($activeCount)
                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand px-1.5 text-[11px] font-semibold text-white" aria-hidden="true">{{ $activeCount }}</span>
                            <span class="sr-only">({{ $activeCount }} filter aktif)</span>
                        @endif
                    </button>
                </div>
            </div>
        </form>

        {{-- Drawer filter: panel kanan di desktop, bottom-sheet di HP. Input memakai atribut form="filter-form". --}}
        <div x-show="filters" x-cloak x-transition.opacity.duration.200ms @click="close()" class="fixed inset-0 z-[70] bg-black/40"></div>
        <div x-show="filters" x-cloak x-ref="panel" @keydown.tab="trap($event)"
             role="dialog" aria-modal="true" aria-labelledby="filter-title"
             x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
             x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full"
             x-transition:enter-end="translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
             x-transition:leave-start="translate-y-0 sm:translate-x-0"
             x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full"
             class="fixed inset-x-0 bottom-0 z-[71] flex max-h-[88vh] flex-col rounded-t-2xl bg-white shadow-2xl sm:inset-y-0 sm:left-auto sm:right-0 sm:max-h-none sm:w-[400px] sm:rounded-none sm:rounded-l-2xl">
            <div class="flex items-center justify-between border-b border-stone-200 px-5 py-3">
                <h2 id="filter-title" class="heading text-xl">Filter</h2>
                <button type="button" @click="close()" class="flex h-11 w-11 items-center justify-center rounded-full text-stone-600 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500" aria-label="Tutup filter">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                <div>
                    <label for="f-brand" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-600">Merek</label>
                    <select id="f-brand" name="brand" form="filter-form" class="field">
                        <option value="">Semua Merek</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->slug }}" @selected(request('brand') == $brand->slug)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>

                <fieldset>
                    <legend class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-600">Harga (Rp)</legend>
                    <div class="flex items-center gap-2">
                        <input type="number" name="min_price" form="filter-form" min="0" inputmode="numeric" value="{{ request('min_price') }}" placeholder="Min" class="field" aria-label="Harga minimum">
                        <span class="text-stone-500" aria-hidden="true">–</span>
                        <input type="number" name="max_price" form="filter-form" min="0" inputmode="numeric" value="{{ request('max_price') }}" placeholder="Maks" class="field" aria-label="Harga maksimum">
                    </div>
                </fieldset>

                <label class="flex min-h-11 cursor-pointer items-center gap-3 text-[15px] text-stone-800">
                    <input type="checkbox" name="in_stock" form="filter-form" value="1" @checked(request()->boolean('in_stock')) class="h-5 w-5 rounded border-stone-300 text-orange-600 focus:ring-orange-500">
                    Hanya yang stoknya tersedia
                </label>
            </div>

            <div class="flex gap-3 border-t border-stone-200 px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <a href="{{ $resetUrl }}" class="btn btn-outline flex-1">Reset</a>
                <button type="submit" form="filter-form" class="btn btn-primary flex-[2]">Terapkan</button>
            </div>
        </div>
    </div>
    @endunless

    <div class="min-w-0 {{ $landing ? '' : 'mt-5' }}">
        @if($landing)
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 class="heading text-2xl sm:text-3xl">Semua produk</h2>
                <span class="text-sm text-stone-500">{{ $products->total() }} produk</span>
            </div>
        @else
            @php
                $chips = array_filter([
                    'brand'     => $brands->firstWhere('slug', request('brand'))->name ?? null,
                    'search'    => request('search') ? '“' . request('search') . '”' : null,
                    'min_price' => request('min_price') ? 'Min Rp ' . number_format((int) request('min_price'), 0, ',', '.') : null,
                    'max_price' => request('max_price') ? 'Maks Rp ' . number_format((int) request('max_price'), 0, ',', '.') : null,
                    'in_stock'  => request()->boolean('in_stock') ? 'Stok tersedia' : null,
                ]);
            @endphp
            <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="text-sm text-stone-600" role="status">{{ $products->total() }} produk</span>
                @foreach($chips as $key => $label)
                    <a href="{{ route('products.index', request()->except($key, 'page')) }}" class="inline-flex min-h-8 items-center gap-1.5 rounded-full border border-stone-300 bg-white px-3 py-1 text-xs text-stone-700 transition hover:border-orange-500" aria-label="Hapus filter {{ $label }}">
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
                <a href="{{ route('products.index') }}" class="mt-3 inline-block text-sm text-orange-600 hover:underline">Reset filter</a>
            </div>
        @endif
    </div>
</div>
@endsection
