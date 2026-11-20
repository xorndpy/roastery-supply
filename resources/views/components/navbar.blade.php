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

{{-- Tanpa backdrop-blur di <header>: efek itu membuat panel menu mobile (fixed) terpotong setinggi header. --}}
<header x-data="{ open: false }" :style="open ? 'z-index:60' : ''"
        @keydown.escape.window="open = false"
        class="sticky top-0 z-40 border-b border-stone-200 bg-white">
    <div class="container-x flex h-16 items-center gap-8 lg:h-20">
        <a href="{{ route('home') }}" class="heading shrink-0 text-[22px] leading-none text-brand">
            @if(!empty($site['site_logo']))
                <img src="{{ media_url($site['site_logo']) }}" alt="{{ $site['site_name'] }}" class="h-14 w-auto lg:h-16">
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
            <button type="button" @click="open = true" class="flex h-11 w-11 items-center justify-center rounded-xl text-stone-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-500" aria-label="Buka menu" aria-haspopup="dialog" :aria-expanded="open.toString()">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Menu mobile: panel samping, bukan layar penuh --}}
    <x-mobile-drawer label="Menu utama">
        <x-slot:header>
            <a href="{{ route('home') }}" class="heading block truncate text-lg leading-none text-brand">
                @if(!empty($site['site_logo']))
                    <img src="{{ media_url($site['site_logo']) }}" alt="{{ $site['site_name'] }}" class="h-9 w-auto">
                @else
                    {{ $site['site_name'] }}
                @endif
            </a>
        </x-slot:header>

        <nav aria-label="Menu mobile">
            @foreach($links as [$label, $route, $pattern])
                @php $active = request()->routeIs($pattern); @endphp
                <a href="{{ route($route) }}" @if($active) aria-current="page" @endif
                   class="flex min-h-12 items-center border-b border-stone-100 border-l-4 pr-5 text-[15px] {{ $active ? 'border-l-copper-500 bg-stone-50 pl-4 font-semibold text-brand' : 'border-l-transparent pl-4 font-medium text-stone-800 hover:bg-stone-50' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if(!empty($site['tagline']))
            <x-slot:footer><p class="text-sm text-stone-600">{{ $site['tagline'] }}</p></x-slot:footer>
        @endif
    </x-mobile-drawer>
</header>
