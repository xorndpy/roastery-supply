@php
    $links = [
        ['Beranda', 'home', 'home'],
        ['Katalog', 'products.index', 'products.*'],
        ['Tentang', 'about', 'about'],
        ['Kontak', 'contact.index', 'contact.*'],
        ['FAQ', 'faq', 'faq'],
    ];
    $cartCount = count((array) session('cart', []));
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur">
    <div class="container-x flex h-16 items-center gap-8">

        {{-- Logo --}}
        <a href="{{ route('home') }}" class="heading shrink-0 text-[22px] leading-none text-brand">
            @if(!empty($site['site_logo']))
                <img src="{{ asset('storage/' . $site['site_logo']) }}" alt="{{ $site['site_name'] }}" class="h-8 w-auto">
            @else
                {{ $site['site_name'] }}
            @endif
        </a>

        {{-- Menu desktop --}}
        <nav class="hidden flex-1 items-center gap-7 text-sm md:flex" aria-label="Menu utama">
            @foreach($links as [$label, $route, $pattern])
                <a href="{{ route($route) }}"
                   class="{{ request()->routeIs($pattern) ? 'font-medium text-stone-900' : 'text-stone-500 hover:text-stone-900' }} transition">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-5 md:ml-0">
            {{-- Keranjang --}}
            <a href="{{ route('customer.cart.index') }}" class="relative text-stone-800 hover:text-orange-500" aria-label="Keranjang belanja">
                <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 4h2.2l2.1 10.2a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.1L20 8H6.2"/>
                    <circle cx="9.5" cy="19.5" r="1.2"/><circle cx="17" cy="19.5" r="1.2"/>
                </svg>
                @if($cartCount > 0)
                    <span class="absolute -right-2.5 -top-2 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-orange-500 px-1 text-[10px] font-semibold text-white">{{ $cartCount }}</span>
                @endif
            </a>

            {{-- Akun (desktop) --}}
            <div class="hidden items-center gap-4 text-sm md:flex">
                @auth
                    <div x-data="{ menu: false }" class="relative">
                        <button @click="menu = !menu" class="flex items-center gap-1.5 text-stone-800 hover:text-orange-500">
                            {{ \Illuminate\Support\Str::words(auth()->user()->name, 1, '') }}
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="menu" x-cloak @click.outside="menu = false" x-transition.opacity
                             class="absolute right-0 mt-3 w-52 overflow-hidden rounded-lg border border-stone-200 bg-white py-1 shadow-lg shadow-stone-900/5">
                            @if(auth()->user()->hasAnyRole(['super_admin', 'admin', 'staff']))
                                <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm font-medium text-orange-600 hover:bg-stone-50">Panel Admin</a>
                            @endif
                            <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Dashboard</a>
                            <a href="{{ route('customer.orders.index') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Pesanan Saya</a>
                            <a href="{{ route('customer.returns.index') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Retur Saya</a>
                            <a href="{{ route('customer.profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Profil</a>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-100">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-stone-50">Keluar</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-stone-800 hover:text-orange-500">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Daftar</a>
                @endauth
            </div>

            {{-- Hamburger --}}
            <button @click="open = !open" class="text-stone-800 md:hidden" aria-label="Menu" :aria-expanded="open">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                    <path x-show="!open" d="M4 7h16M4 12h16M4 17h16"/>
                    <path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Menu mobile --}}
    <div x-show="open" x-cloak x-transition.opacity class="border-t border-stone-200 bg-white md:hidden">
        <div class="container-x py-2">
            @foreach($links as [$label, $route, $pattern])
                <a href="{{ route($route) }}" class="block border-b border-stone-100 py-3 text-sm {{ request()->routeIs($pattern) ? 'font-medium text-stone-900' : 'text-stone-600' }}">{{ $label }}</a>
            @endforeach
            <div class="flex items-center gap-4 py-3 text-sm">
                @auth
                    <a href="{{ route('customer.dashboard') }}" class="font-medium text-stone-900">Akun Saya</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-red-600">Keluar</button></form>
                @else
                    <a href="{{ route('login') }}" class="text-stone-800">Masuk</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Daftar</a>
                @endauth
            </div>
        </div>
    </div>
</header>
