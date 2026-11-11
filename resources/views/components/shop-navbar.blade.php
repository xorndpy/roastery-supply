{{-- Navbar TOKO. Jangan pakai backdrop-blur di <header>: itu membuat menu mobile (fixed) terpotong. --}}
@php
    $cartCount = count((array) session('cart', []));
    $wc = ['products' => fn ($q) => $q->where('is_active', true)];
    // Menu kategori jarang berubah: simpan 5 menit supaya tidak query berat di setiap halaman.
    $navCats = \Illuminate\Support\Facades\Cache::remember('shop.nav.categories', 300, function () use ($wc) {
        return \App\Models\Category::where('is_active', true)->whereNull('parent_id')
            ->withCount($wc)->with(['children' => fn ($q) => $q->where('is_active', true)->withCount($wc)])
            ->orderBy('name')->get()
            ->filter(fn ($c) => $c->products_count + $c->children->sum('products_count') > 0)
            ->take(12)->values();
    });
    $navLink = 'rounded-full px-3.5 py-2 text-stone-600 transition hover:bg-stone-100 hover:text-stone-900';
@endphp

<header x-data="{ open: false, cat: false }" @keydown.escape.window="open = false; cat = false" class="sticky top-0 z-40 border-b border-stone-200 bg-white">
    <div class="container-x flex h-16 items-center gap-4 lg:gap-8">
        <a href="{{ route('products.index') }}" class="shrink-0 leading-none">
            @if(!empty($site['site_logo']))
                <img src="{{ media_url($site['site_logo']) }}" alt="{{ $site['site_name'] }}" class="h-8 w-auto">
            @else
                <span class="heading block text-[22px] text-brand">{{ $site['site_name'] }}</span>
            @endif
        </a>

        {{-- Menu desktop --}}
        <nav class="hidden items-center gap-1 text-sm lg:flex" aria-label="Menu toko">
            <a href="{{ route('home') }}" class="{{ $navLink }}">Beranda</a>
            <a href="{{ route('products.index') }}" class="{{ $navLink }} {{ request()->routeIs('products.index') ? '!bg-stone-100 font-medium !text-stone-900' : '' }}">Produk</a>

            <div class="relative" @mouseenter="cat = true" @mouseleave="cat = false">
                <button type="button" @click="cat = !cat" :aria-expanded="cat" class="{{ $navLink }} inline-flex items-center gap-1.5">
                    Kategori
                    <svg class="h-3.5 w-3.5 transition" :class="cat && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div x-show="cat" x-cloak x-transition.opacity class="absolute left-0 top-full w-[640px] pt-2">
                    <div class="grid grid-cols-3 gap-x-6 gap-y-6 rounded-2xl border border-stone-200 bg-white p-6 shadow-xl shadow-stone-900/10">
                        @foreach($navCats as $c)
                            <div>
                                <a href="{{ route('products.index', ['category' => $c->slug]) }}" class="text-sm font-semibold text-stone-900 hover:text-orange-600">{{ $c->name }}</a>
                                @foreach($c->children->take(5) as $ch)
                                    <a href="{{ route('products.index', ['category' => $ch->slug]) }}" class="mt-1.5 block text-[13px] text-stone-500 hover:text-orange-600">{{ $ch->name }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <a href="{{ route('products.brands') }}" class="{{ $navLink }} {{ request()->routeIs('products.brands') ? '!bg-stone-100 font-medium !text-stone-900' : '' }}">Merek</a>
            <a href="{{ route('faq') }}" class="{{ $navLink }}">Bantuan</a>
        </nav>

        {{-- Pencarian desktop --}}
        <form action="{{ route('products.index') }}" method="GET" role="search" class="hidden flex-1 md:block lg:ml-auto lg:max-w-md">
            <label for="shop-search" class="sr-only">Cari produk</label>
            <div class="relative">
                <input id="shop-search" type="search" name="search" value="{{ request('search') }}" placeholder="Cari produk, SKU…" autocomplete="off"
                       class="h-10 w-full rounded-full border border-stone-300 bg-stone-50 pl-4 pr-12 text-sm placeholder:text-stone-500 focus:border-orange-500 focus:bg-white focus:ring-4 focus:ring-orange-500/15">
                <button type="submit" class="absolute right-1 top-1 flex h-8 w-8 items-center justify-center rounded-full bg-brand text-white transition hover:bg-brand-dark" aria-label="Cari">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>
                </button>
            </div>
        </form>

        <div class="ml-auto flex items-center gap-4 md:ml-0">
            <a href="{{ route('customer.cart.index') }}" class="relative flex items-center gap-2 text-stone-800 hover:text-orange-600" aria-label="Keranjang belanja">
                <svg class="h-[24px] w-[24px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 4h2.2l2.1 10.2a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.1L20 8H6.2"/><circle cx="9.5" cy="19.5" r="1.2"/><circle cx="17" cy="19.5" r="1.2"/>
                </svg>
                @if($cartCount > 0)
                    <span class="absolute -right-2.5 -top-2 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-orange-500 px-1 text-[10px] font-semibold text-white">{{ $cartCount }}</span>
                @endif
            </a>

            <div class="hidden items-center gap-3 text-sm lg:flex">
                @auth
                    <div x-data="{ menu: false }" class="relative">
                        <button @click="menu = !menu" class="flex items-center gap-1.5 text-stone-800 hover:text-orange-600" :aria-expanded="menu">
                            {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="menu" x-cloak @click.outside="menu = false" x-transition.opacity class="absolute right-0 mt-3 w-52 overflow-hidden rounded-lg border border-stone-200 bg-white py-1 shadow-lg shadow-stone-900/5">
                            @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'staff', 'gudang']))
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm font-medium text-orange-600 hover:bg-stone-50">Panel Admin</a>
                            @endif
                            <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Dashboard</a>
                            <a href="{{ route('customer.orders.index') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Pesanan Saya</a>
                            <a href="{{ route('customer.returns.index') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Retur Saya</a>
                            <a href="{{ route('customer.profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Profil</a>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-100">@csrf
                                <button type="submit" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-stone-50">Keluar</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-stone-800 hover:text-orange-600">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm !rounded-full">Daftar</a>
                @endauth
            </div>

            {{-- Tombol menu mobile --}}
            <button @click="open = true" class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand text-white lg:hidden" aria-label="Buka menu" :aria-expanded="open">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 8h16M4 16h10"/></svg>
            </button>
        </div>
    </div>

    {{-- Pencarian mobile --}}
    <form action="{{ route('products.index') }}" method="GET" role="search" class="container-x pb-3 md:hidden">
        <label for="shop-search-m" class="sr-only">Cari produk</label>
        <input id="shop-search-m" type="search" name="search" value="{{ request('search') }}" placeholder="Cari produk…" class="h-10 w-full rounded-full border border-stone-300 bg-stone-50 px-4 text-sm focus:border-orange-500 focus:ring-4 focus:ring-orange-500/15">
    </form>

    {{-- Menu mobile layar penuh --}}
    <div x-show="open" x-cloak x-transition.opacity x-data="{ prod: false }" class="fixed inset-0 z-50 overflow-y-auto bg-white lg:hidden">
        <div class="flex h-16 items-center justify-between px-4">
            <span class="heading text-xl text-brand">{{ $site['site_name'] }}</span>
            <button @click="open = false" class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand text-white" aria-label="Tutup menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <nav class="px-6 pb-12 pt-2 text-[15px] font-semibold uppercase tracking-wide text-stone-900" aria-label="Menu mobile">
            <button type="button" @click="prod = !prod" class="flex w-full items-center justify-between border-b border-stone-100 py-4 uppercase" :aria-expanded="prod">
                Produk
                <svg class="h-4 w-4 transition" :class="prod && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div x-show="prod" x-cloak class="border-b border-stone-100 pb-3 text-sm font-normal normal-case tracking-normal">
                <a href="{{ route('products.index') }}" class="block py-2 font-medium text-orange-600">Semua produk</a>
                @foreach($navCats as $c)
                    <a href="{{ route('products.index', ['category' => $c->slug]) }}" class="block py-2 text-stone-800">{{ $c->name }}</a>
                    @foreach($c->children->take(5) as $ch)
                        <a href="{{ route('products.index', ['category' => $ch->slug]) }}" class="block py-1.5 pl-4 text-stone-500">{{ $ch->name }}</a>
                    @endforeach
                @endforeach
            </div>

            <a href="{{ route('products.brands') }}" class="block border-b border-stone-100 py-4">Merek</a>
            <a href="{{ route('home') }}" class="block border-b border-stone-100 py-4">Beranda</a>
            <a href="{{ route('about') }}" class="block border-b border-stone-100 py-4">Tentang Kami</a>
            <a href="{{ route('faq') }}" class="block border-b border-stone-100 py-4">Bantuan</a>
            <a href="{{ route('contact.index') }}" class="block border-b border-stone-100 py-4">Hubungi Kami</a>

            <div class="pt-6 text-sm font-medium normal-case tracking-normal">
                @auth
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('customer.orders.index') }}" class="rounded-xl bg-stone-100 px-4 py-3 text-center">Pesanan Saya</a>
                        <a href="{{ route('customer.dashboard') }}" class="rounded-xl bg-stone-100 px-4 py-3 text-center">Akun Saya</a>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf
                        <button class="w-full rounded-xl border border-red-200 px-4 py-3 text-red-600">Keluar</button>
                    </form>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <a href="{{ route('login') }}" class="btn btn-outline !rounded-xl">Masuk</a>
                        <a href="{{ route('register') }}" class="btn btn-primary !rounded-xl">Daftar</a>
                    </div>
                @endauth
            </div>
        </nav>
    </div>
</header>
