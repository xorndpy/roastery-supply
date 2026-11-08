@extends('layouts.shop')

@section('title', 'Katalog Produk')

@section('content')

@if($landing)
    {{-- HERO (besar, gaya beranda: pakai background dari Admin > Pengaturan > Tampilan) --}}
    @php
        $brandCards = $brandCards ?? collect(); // aman walau controller belum diperbarui
        // Slide otomatis: produk terbaru (cadangan: unggulan) yang punya foto
        $heroSlides = ($latest->isNotEmpty() ? $latest : $featured)->filter(fn ($p) => $p->images->isNotEmpty())->take(6)->values();
        // Kartu kategori hanya tampil kalau sudah punya foto
        $heroCats = $categoryCards->filter(fn ($c) => ! empty($c->image))->values();
    @endphp
    <section class="relative isolate flex min-h-[560px] flex-col overflow-hidden bg-[#1a0f0a] text-white lg:min-h-[78vh]">
        @if(!empty($site['hero_bg']))
            <img src="{{ media_url($site['hero_bg']) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover" fetchpriority="high">
            <div class="absolute inset-0 -z-10 bg-[#1a0f0a]/45 lg:hidden"></div>
            <div class="absolute inset-0 -z-10 hidden bg-gradient-to-r from-[#1a0f0a]/55 via-[#1a0f0a]/30 via-[46%] to-[#1a0f0a]/0 lg:block"></div>
            <div class="absolute inset-x-0 bottom-0 -z-10 h-1/3 bg-gradient-to-t from-[#1a0f0a]/70 to-transparent"></div>
        @else
            <div class="absolute inset-0 -z-20 bg-gradient-to-br from-[#24150e] via-[#35200f] to-brand"></div>
            <div class="auth-beans absolute inset-0 -z-10"></div>
            <div class="absolute -right-24 top-1/4 -z-10 h-[28rem] w-[28rem] rounded-full bg-orange-500/20 blur-3xl"></div>
        @endif

        <div class="container-x flex w-full flex-1 items-center py-16 sm:py-20">
            <div class="grid w-full items-center gap-10 {{ $heroSlides->isNotEmpty() ? 'lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-14' : '' }}">

                <div class="min-w-0">
                    {{-- Judul disembunyikan dari tampilan (tetap dibaca mesin pencari & pembaca layar) --}}
                    <h1 class="sr-only">Toko {{ $site['site_name'] }}: lengkapi kedai kopi Anda dari satu toko.</h1>

                    @if($heroCats->isNotEmpty())
                        <div class="auth-rise" style="--d:120ms">
                            <x-rail>
                                @foreach($heroCats as $c)
                                    <a href="{{ route('products.index', ['category' => $c->slug]) }}" class="group w-[104px] shrink-0 snap-start text-center sm:w-[132px]">
                                        <div class="aspect-square overflow-hidden rounded-2xl bg-white/10 ring-1 ring-white/10 transition group-hover:ring-orange-300">
                                            <img src="{{ media_url($c->image) }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                        </div>
                                        <span class="mt-2.5 block text-[13px] font-medium text-stone-200 group-hover:text-white">{{ $c->name }}</span>
                                    </a>
                                @endforeach
                            </x-rail>
                        </div>
                    @endif
                </div>

                @if($heroSlides->isNotEmpty())
                    {{-- Slide produk otomatis (geser sendiri tiap 4,5 detik, berhenti saat disorot) --}}
                    <div class="relative mx-auto w-full max-w-sm lg:max-w-none"
                         x-data="{ i: 0, n: {{ $heroSlides->count() }}, timer: null,
                                   next() { this.i = (this.i + 1) % this.n },
                                   play() { if (this.n > 1 && !this.timer) this.timer = setInterval(() => this.next(), 4500) },
                                   stop() { clearInterval(this.timer); this.timer = null } }"
                         x-init="play()" @mouseenter="stop()" @mouseleave="play()">
                        <div class="relative grid aspect-[4/5] grid-rows-1 overflow-hidden rounded-3xl bg-white/5 shadow-2xl shadow-black/30 ring-1 ring-white/10">
                            @foreach($heroSlides as $p)
                                @php $img = $p->images->first(); @endphp
                                <a href="{{ route('products.show', $p->slug) }}"
                                   class="col-start-1 row-start-1 flex h-full flex-col transition-opacity duration-700"
                                   :class="i === {{ $loop->index }} ? 'opacity-100' : 'pointer-events-none opacity-0'"
                                   :aria-hidden="i !== {{ $loop->index }}" :tabindex="i === {{ $loop->index }} ? 0 : -1">
                                    <div class="flex min-h-0 flex-1 items-center justify-center bg-white p-6">
                                        <img src="{{ media_url($img->image) }}" alt="{{ $p->name }}" @if(! $loop->first) loading="lazy" @endif class="max-h-full max-w-full object-contain">
                                    </div>
                                    <div class="bg-black/40 px-5 py-4">
                                        <span class="eyebrow !text-orange-300">Terbaru</span>
                                        <div class="heading mt-1 line-clamp-1 text-lg">{{ $p->name }}</div>
                                        <div class="mt-0.5 text-[15px] font-semibold text-orange-300">Rp {{ number_format($p->price, 0, ',', '.') }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        @if($heroSlides->count() > 1)
                            <div class="mt-4 flex justify-center gap-2">
                                @foreach($heroSlides as $p)
                                    <button type="button" @click="i = {{ $loop->index }}" class="h-2 rounded-full transition-all"
                                            :class="i === {{ $loop->index }} ? 'w-6 bg-orange-300' : 'w-2 bg-white/30 hover:bg-white/50'"
                                            aria-label="Tampilkan produk {{ $loop->iteration }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </section>

    {{-- MEREK (dari Admin > Merek), tepat di bawah hero --}}
    @if($brandCards->isNotEmpty())
        <section class="container-x pt-14" aria-labelledby="merek-title">
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 id="merek-title" class="heading text-2xl sm:text-3xl"><span class="text-orange-500">Merek.</span> Pilih yang Anda percaya.</h2>
                <a href="{{ route('products.brands') }}" class="hidden text-sm font-medium text-orange-700 hover:underline sm:block">Lihat semua merek →</a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-6">
                @foreach($brandCards as $b)
                    <a href="{{ route('products.index', ['brand' => $b->slug]) }}"
                       class="group flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-2xl border border-stone-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-orange-500 hover:shadow-lg hover:shadow-stone-900/5">
                        @if($b->logo)
                            <img src="{{ media_url($b->logo) }}" alt="{{ $b->name }}" loading="lazy" class="max-h-14 max-w-[80%] object-contain opacity-70 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">
                        @else
                            <span class="heading text-center text-lg leading-tight text-stone-800">{{ $b->name }}</span>
                        @endif
                        <span class="text-[11px] text-stone-500">{{ $b->products_count }} produk</span>
                    </a>
                @endforeach
            </div>
            <p class="mt-4 text-center sm:hidden"><a href="{{ route('products.brands') }}" class="text-sm font-medium text-orange-700 hover:underline">Lihat semua merek →</a></p>
        </section>
    @endif

    {{-- TERBARU --}}
    @if($latest->isNotEmpty())
        <section class="container-x pt-14" aria-labelledby="terbaru-title">
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
                <div class="w-[240px] shrink-0 snap-start rounded-2xl bg-gradient-to-br from-orange-400 to-orange-600 p-5 text-white sm:w-[calc(25%-12px)]">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                    <p class="mt-4 text-[15px] font-medium leading-snug">{{ $text }}</p>
                </div>
            @endforeach
        </x-rail>
    </section>
@else
    <div class="border-b border-stone-200 bg-white">
        <div class="container-x py-6">
            <h1 class="heading text-2xl sm:text-[1.9rem]">{{ request('search') ? 'Hasil untuk “' . request('search') . '”' : 'Katalog Produk' }}</h1>
            <p class="mt-1 text-sm text-stone-600">{{ $site['tagline'] }}</p>
        </div>
    </div>
@endif

{{-- SEMUA PRODUK --}}
<div id="semua" class="container-x py-10 {{ $landing ? '' : 'grid gap-10 lg:grid-cols-[240px_1fr]' }}" x-data="{ filters: false }">

    @unless($landing)
    <aside class="lg:sticky lg:top-24 lg:self-start">
        <button type="button" @click="filters = true" class="btn btn-outline btn-sm mb-4 w-full !rounded-full lg:hidden">Filter &amp; Urutkan</button>

        <div x-show="filters" x-cloak x-transition.opacity @click="filters = false" class="fixed inset-0 z-50 bg-black/40 lg:hidden"></div>

        <form id="filter-form" method="GET" action="{{ route('products.index') }}"
              class="space-y-5 bg-white p-5 lg:static lg:block lg:rounded-xl lg:border lg:border-stone-200"
              :class="filters ? 'fixed inset-x-0 bottom-0 z-[60] block max-h-[85vh] overflow-y-auto rounded-t-2xl shadow-2xl' : 'hidden'">
            <div class="flex items-center justify-between">
                <b class="text-sm">Filter</b>
                <div class="flex items-center gap-4">
                    <a href="{{ route('products.index') }}" class="text-[13px] text-orange-600 hover:underline">Reset</a>
                    <button type="button" @click="filters = false" class="text-sm text-stone-500 lg:hidden" aria-label="Tutup">✕</button>
                </div>
            </div>

            <div>
                <label for="f-search" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-500">Cari</label>
                <input id="f-search" type="text" name="search" value="{{ request('search') }}" placeholder="Nama, SKU, deskripsi…" class="field">
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

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-500">Harga (Rp)</label>
                <div class="flex items-center gap-2">
                    <input type="number" name="min_price" min="0" value="{{ request('min_price') }}" placeholder="Min" class="field" aria-label="Harga minimum">
                    <span class="text-stone-400">–</span>
                    <input type="number" name="max_price" min="0" value="{{ request('max_price') }}" placeholder="Maks" class="field" aria-label="Harga maksimum">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock')) class="rounded border-stone-300 text-orange-600 focus:ring-orange-500">
                Hanya yang stoknya tersedia
            </label>

            <button type="submit" class="btn btn-primary w-full">Terapkan Filter</button>
        </form>
    </aside>
    @endunless

    <div class="min-w-0">
        @unless($landing)
        {{-- Chip kategori (HP) --}}
        <div class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 text-sm [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:hidden">
            <a href="{{ route('products.index', request()->except('page', 'category')) }}" class="shrink-0 rounded-full px-4 py-1.5 {{ !request('category') ? 'bg-brand text-white' : 'border border-stone-300 bg-white text-stone-600' }}">Semua</a>
            @foreach($categories as $cat)
                <a href="{{ route('products.index', array_merge(request()->except('page'), ['category' => $cat->slug])) }}" class="shrink-0 rounded-full px-4 py-1.5 {{ request('category') === $cat->slug ? 'bg-brand text-white' : 'border border-stone-300 bg-white text-stone-600' }}">{{ $cat->name }}</a>
            @endforeach
        </div>

        @endunless

        @if($landing)
            <div class="mb-6 flex items-end justify-between gap-4">
                <h2 class="heading text-2xl sm:text-3xl">Semua produk</h2>
                <span class="text-sm text-stone-500">{{ $products->total() }} produk</span>
            </div>
        @else
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
        @endif

        @php
            $chips = array_filter([
                'category'  => $categories->firstWhere('slug', request('category'))->name ?? null,
                'brand'     => $brands->firstWhere('slug', request('brand'))->name ?? null,
                'search'    => request('search') ? '“' . request('search') . '”' : null,
                'min_price' => request('min_price') ? 'Min Rp ' . number_format((int) request('min_price'), 0, ',', '.') : null,
                'max_price' => request('max_price') ? 'Maks Rp ' . number_format((int) request('max_price'), 0, ',', '.') : null,
                'in_stock'  => request()->boolean('in_stock') ? 'Stok tersedia' : null,
            ]);
        @endphp
        @if($chips)
            <div class="mb-5 flex flex-wrap gap-2">
                @foreach($chips as $key => $label)
                    <a href="{{ route('products.index', request()->except($key, 'page')) }}" class="inline-flex items-center gap-1.5 rounded-full border border-stone-300 bg-white px-3 py-1 text-xs text-stone-700 transition hover:border-orange-500">
                        {{ $label }} <span aria-hidden="true">×</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if($products->count())
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:gap-5 {{ $landing ? 'sm:grid-cols-3 lg:grid-cols-4' : 'xl:grid-cols-3' }}">
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
