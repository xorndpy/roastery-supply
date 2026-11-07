{{-- Navbar COMPANY PROFILE. Tanpa keranjang dan login: itu urusan toko (lihat x-shop-navbar). --}}
@php
    $links = [
        ['Beranda', 'home', 'home'],
        ['Produk', 'products.index', 'products.*'],
        ['Tentang', 'about', 'about'],
        ['Artikel', 'articles.index', 'articles.*'],
        ['Cabang', 'branches.index', 'branches.*'],
        ['FAQ', 'faq', 'faq'],
        ['Kontak', 'contact.index', 'contact.*'],
    ];
@endphp

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur">
    <div class="container-x flex h-16 items-center gap-8">
        <a href="{{ route('home') }}" class="heading shrink-0 text-[22px] leading-none text-brand">
            @if(!empty($site['site_logo']))
                <img src="{{ asset('storage/' . $site['site_logo']) }}" alt="{{ $site['site_name'] }}" class="h-8 w-auto">
            @else
                {{ $site['site_name'] }}
            @endif
        </a>

        <nav class="hidden flex-1 items-center gap-7 text-sm lg:flex" aria-label="Menu utama">
            @foreach($links as [$label, $route, $pattern])
                <a href="{{ route($route) }}" @if(request()->routeIs($pattern)) aria-current="page" @endif
                   class="{{ request()->routeIs($pattern) ? 'font-medium text-stone-900' : 'text-stone-500 hover:text-stone-900' }} transition">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-4 lg:hidden">
            <button @click="open = !open" class="text-stone-800" aria-label="Menu" :aria-expanded="open">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                    <path x-show="!open" d="M4 7h16M4 12h16M4 17h16"/><path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-transition.opacity class="border-t border-stone-200 bg-white lg:hidden">
        <div class="container-x py-2">
            @foreach($links as [$label, $route, $pattern])
                <a href="{{ route($route) }}" class="block border-b border-stone-100 py-3 text-sm last:border-0 {{ request()->routeIs($pattern) ? 'font-medium text-stone-900' : 'text-stone-600' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</header>
