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
<header x-data="{ open: false }"
        x-effect="document.body.classList.toggle('overflow-hidden', open)"
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
            <button @click="open = !open" class="flex h-10 w-10 items-center justify-center rounded-xl text-stone-800" aria-label="Menu" :aria-expanded="open">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                    <path x-show="!open" d="M4 7h16M4 12h16M4 17h16"/><path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Menu mobile: memanjang sampai bawah layar, header tetap di tempatnya --}}
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-x-0 bottom-0 top-16 z-40 overflow-y-auto bg-white lg:hidden">
        <nav class="container-x flex min-h-full flex-col py-2" aria-label="Menu mobile">
            @foreach($links as [$label, $route, $pattern])
                <a href="{{ route($route) }}" @if(request()->routeIs($pattern)) aria-current="page" @endif
                   class="heading flex items-center justify-between border-b border-stone-100 py-4 text-[26px] leading-tight {{ request()->routeIs($pattern) ? 'text-brand' : 'text-stone-800' }}">
                    <span class="flex items-center gap-3">
                        @if(request()->routeIs($pattern))<span class="h-2 w-2 rounded-full bg-orange-500"></span>@endif
                        {{ $label }}
                    </span>
                    <svg class="h-5 w-5 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                </a>
            @endforeach

            <p class="mt-auto pb-8 pt-10 text-sm text-stone-500">{{ $site['tagline'] ?? '' }}</p>
        </nav>
    </div>
</header>
