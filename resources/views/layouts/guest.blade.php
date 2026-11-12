@php
    $name = $site['site_name'] ?? config('app.name');

    // Foto panel brand. Urutan prioritas:
    //   1) public/images/auth-bg.{webp,jpg,jpeg,png}   <- taruh foto Anda di sini
    //   2) Foto pertama slideshow dari Admin > Hero Beranda
    //   3) tanpa foto: gradasi espresso + pola biji kopi
    $authPhoto = null;
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        if (file_exists(public_path("images/auth-bg.$ext"))) {
            $authPhoto = asset("images/auth-bg.$ext");
            break;
        }
    }
    try {
        $heroFirst = \App\Models\HeroSlide::active()->value('image');
    } catch (\Throwable) {
        $heroFirst = null;
    }
    $authPhoto ??= $heroFirst ? media_url($heroFirst) : null;

    // Judul panel brand bisa diganti per halaman lewat <x-slot:headline>.
    $headline = $headline ?? ($site['tagline'] ?? 'Mesin kopi profesional untuk kedai kopi serius.');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-favicon />
</head>
<body class="bg-stone-50 font-sans text-stone-900 antialiased">
    <a href="#main" class="sr-only z-50 rounded-md bg-white px-4 py-2 text-sm font-medium text-brand shadow focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke formulir</a>

    <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

        {{-- ============ Panel brand (desktop) ============ --}}
        <aside class="relative hidden overflow-hidden bg-[#24150e] text-white lg:sticky lg:top-0 lg:block lg:h-screen">

            <div class="absolute inset-0">
                @include('auth.partials.backdrop', ['photo' => $authPhoto])
            </div>
            {{-- Overlay menjaga kontras teks putih tetap tinggi di atas foto apa pun --}}
            <div class="absolute inset-0 bg-gradient-to-t from-[#1a0f0a] via-[#24150e]/65 to-[#24150e]/25"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-[#24150e]/70 to-transparent"></div>

            <div class="relative z-10 flex h-full flex-col justify-between p-12 xl:p-14">
                <a href="{{ route('home') }}" class="heading auth-rise inline-block w-fit text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-300 focus-visible:ring-offset-2 focus-visible:ring-offset-[#24150e]">
                    {{ $name }}
                </a>

                <div>
                    <h2 class="heading auth-rise max-w-md text-4xl leading-[1.15]" style="--d:120ms">{{ $headline }}</h2>

                    <ul class="mt-10 max-w-sm space-y-1 text-sm text-stone-100">
                        @foreach ([
                            ['Pesanan dan pengiriman terpantau di satu akun', '<path d="M3.5 7.5l8.5-4 8.5 4v9l-8.5 4-8.5-4v-9z"/><path d="M3.5 7.5L12 11.5l8.5-4M12 11.5v9"/>'],
                            ['Garansi dan servis tercatat per produk', '<path d="M12 3.5l7.5 3v5.4c0 4.4-3.1 7.6-7.5 8.6-4.4-1-7.5-4.2-7.5-8.6V6.5l7.5-3z"/><path d="M8.8 12l2.2 2.2 4.2-4.4"/>'],
                            ['Harga dan stok langsung dari distributor', '<path d="M3.5 12.4V4.5h7.9l9.1 9.1-7.9 7.9-9.1-9.1z"/><circle cx="8" cy="9" r="1.2"/>'],
                        ] as $i => [$text, $icon])
                            <li class="auth-rise flex items-center gap-3.5 py-2.5" style="--d:{{ 260 + $i * 110 }}ms">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20 bg-white/10 text-orange-200 ">
                                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                </span>
                                {{ $text }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="text-xs text-stone-300">&copy; {{ date('Y') }} {{ $name }}</div>
            </div>
        </aside>

        {{-- ============ Kolom form ============ --}}
        <div class="relative flex flex-col">

            {{-- Header foto (mobile) --}}
            <header class="relative h-44 shrink-0 overflow-hidden bg-[#24150e] text-white lg:hidden">
                <div class="absolute -inset-4">@include('auth.partials.backdrop', ['photo' => $authPhoto])</div>
                <div class="absolute inset-0 bg-gradient-to-t from-[#1a0f0a] via-[#24150e]/55 to-[#24150e]/20"></div>
                <div class="relative z-10 flex h-full flex-col justify-end px-6 pb-9">
                    <a href="{{ route('home') }}" class="heading text-xl">{{ $name }}</a>
                    <p class="mt-1 line-clamp-2 text-sm text-stone-100">{{ $headline }}</p>
                </div>
            </header>

            <main id="main" class="relative -mt-5 flex-1 rounded-t-[1.75rem] bg-stone-50 px-6 pb-12 pt-8 sm:px-10 lg:mt-0 lg:flex lg:items-center lg:justify-center lg:rounded-none lg:px-12 lg:py-12">
                {{-- cahaya hangat tipis di sudut --}}
                <div class="pointer-events-none absolute right-0 top-0 hidden h-72 w-72 bg-[radial-gradient(circle_at_100%_0%,rgba(168,100,58,0.10),transparent_70%)] lg:block"></div>
                <div class="relative mx-auto w-full max-w-[26rem]">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    <x-confirm-dialog />
</body>
</html>
