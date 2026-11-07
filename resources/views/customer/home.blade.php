@extends('layouts.app')

@section('title', $site['site_name'])

@section('content')
@php
    // Tiga poin penjelas di dasar hero. Ubah sesuai kenyataan usaha Anda.
    $pillars = [
        ['Produk resmi', 'Dipasok dari distributor resmi dan dilengkapi garansi resmi.'],
        ['Konsultasi & instalasi', 'Rekomendasi sesuai kapasitas usaha, pemasangan di lokasi, dan pelatihan barista.'],
        ['Purna jual', 'Teknisi dan suku cadang tersedia untuk perawatan jangka panjang.'],
    ];
    $services = [
        ['Konsultasi kebutuhan', 'Rekomendasi mesin dan peralatan disesuaikan dengan kapasitas, konsep usaha, dan anggaran Anda.'],
        ['Produk resmi bergaransi', 'Setiap unit dipasok dari distributor resmi dan dilengkapi garansi resmi.'],
        ['Pengiriman terasuransi', 'Unit dikemas rapi dan diasuransikan selama proses pengiriman hingga lokasi Anda.'],
        ['Instalasi, servis, dan suku cadang', 'Pemasangan di lokasi, pelatihan dasar bagi barista, serta teknisi dan suku cadang untuk perawatan jangka panjang.'],
    ];
@endphp

{{-- ============ HERO ============ --}}
<section class="relative isolate flex min-h-[640px] flex-col overflow-hidden bg-[#1a0f0a] text-white lg:min-h-[calc(100vh-4rem)]">
    @if(!empty($site['hero_bg']))
        <img src="{{ asset('storage/' . $site['hero_bg']) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover" fetchpriority="high">
        <div class="absolute inset-0 -z-10 bg-[#1a0f0a]/80 lg:hidden"></div>
        <div class="absolute inset-0 -z-10 hidden bg-gradient-to-r from-[#1a0f0a]/90 via-[#1a0f0a]/75 via-[46%] to-[#1a0f0a]/0 lg:block"></div>
        <div class="absolute inset-x-0 bottom-0 -z-10 h-1/3 bg-gradient-to-t from-[#1a0f0a]/70 to-transparent"></div>
    @else
        <div class="absolute inset-0 -z-20 bg-gradient-to-br from-[#24150e] via-[#35200f] to-brand"></div>
        <div class="auth-beans absolute inset-0 -z-10"></div>
        <div class="absolute -right-24 top-1/4 -z-10 h-[28rem] w-[28rem] rounded-full bg-orange-500/20 blur-3xl"></div>
    @endif

    <div class="container-x flex w-full flex-1 items-center py-20">
        <div class="max-w-2xl">
            <p class="eyebrow auth-rise !text-orange-300">{{ $site['site_name'] }}</p>
            <h1 class="heading auth-rise mt-5 text-[2.75rem] leading-[1.05] [text-wrap:balance] sm:text-6xl lg:text-[4.25rem]" style="--d:70ms">{{ $site['tagline'] }}</h1>
            <p class="auth-rise mt-6 max-w-xl text-lg leading-relaxed text-stone-100" style="--d:140ms">{{ $site['hero_text'] }}</p>
            <div class="auth-rise mt-9 flex flex-wrap gap-3" style="--d:210ms">
                <a href="{{ route('products.index') }}" class="btn btn-light btn-lg">Lihat Katalog</a>
                <a href="{{ route('about') }}" class="btn btn-ghost-light btn-lg">Kenali Kami</a>
            </div>
        </div>
    </div>

    {{-- Penjelasan singkat, menggantikan model 3D --}}
    <div class="border-t border-white/15 bg-[#1a0f0a]/75 backdrop-blur-sm">
        <div class="container-x grid divide-white/15 sm:grid-cols-3 sm:divide-x">
            @foreach($pillars as $i => [$t, $d])
                <div class="py-5 sm:px-6 {{ $i === 0 ? 'sm:pl-0' : '' }} {{ $i === 2 ? 'sm:pr-0' : '' }}">
                    <p class="text-sm font-semibold text-white">{{ $t }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-stone-200">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ PERKENALAN & LAYANAN ============ --}}
<section class="container-x pt-24" aria-labelledby="kenali-title">
    <div class="grid gap-10 lg:grid-cols-[5fr_7fr] lg:gap-20">
        <div class="lg:sticky lg:top-28 lg:self-start">
            <p class="eyebrow">Mengenal kami</p>
            <h2 id="kenali-title" class="heading mt-3 text-3xl leading-[1.15] [text-wrap:balance] sm:text-[2.5rem]">{{ $site['home_intro_title'] }}</h2>
            <p class="mt-4 max-w-md text-[15px] leading-relaxed text-stone-600">{{ $site['home_intro_body'] }}</p>
            <a href="{{ route('about') }}" class="mt-6 inline-flex items-center gap-1.5 text-sm font-medium text-orange-700 transition hover:gap-2.5 hover:underline">Tentang {{ $site['site_name'] }} <span aria-hidden="true">→</span></a>
        </div>

        <ol class="svc-list">
            @foreach($services as $i => [$title, $desc])
                <li class="svc-row" data-reveal style="--d: {{ $i * 90 }}ms">
                    <span class="svc-no" aria-hidden="true">0{{ $i + 1 }}</span>
                    <div>
                        <h3 class="svc-title">{{ $title }}</h3>
                        <p class="svc-desc">{{ $desc }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ============ BANNER LEBAR (Admin > Banner, maks. 2) ============ --}}
@if($banners->isNotEmpty())
    <section class="container-x pt-24" aria-label="Sorotan">
        <div class="space-y-5">
            @foreach($banners as $b)
                @php $img = '<img src="' . e(asset('storage/' . $b->image)) . '" alt="' . e($b->title) . '" loading="lazy" class="block h-auto w-full rounded-2xl">'; @endphp
                <div data-reveal>
                    @if($b->link)
                        <a href="{{ $b->link }}" class="block rounded-2xl transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">{!! $img !!}</a>
                    @else
                        {!! $img !!}
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif

{{-- ============ MILESTONE ============ --}}
@if($milestones->isNotEmpty())
    <section class="container-x pt-24" aria-labelledby="ms-title">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="eyebrow">Perjalanan kami</p>
            <h2 id="ms-title" class="heading mt-3 text-3xl leading-[1.15] [text-wrap:balance] sm:text-[2.5rem]">Langkah demi langkah menuju layanan yang andal.</h2>
        </div>
        <div class="mt-12">
            <x-milestones :items="$milestones->take(4)" compact />
        </div>
        <p class="mt-6 text-center"><a href="{{ route('about') }}#perjalanan" class="text-sm font-medium text-orange-700 hover:underline">Lihat perjalanan selengkapnya →</a></p>
    </section>
@endif

{{-- ============ MEREK & REKANAN ============ --}}
@if($partners->isNotEmpty())
    <section class="container-x pt-24" aria-labelledby="brand-title" x-data="{ scroll(d) { $refs.track.scrollBy({ left: d * $refs.track.clientWidth * 0.8, behavior: 'smooth' }) } }">
        <div class="text-center" data-reveal>
            <p class="eyebrow">Merek & rekanan</p>
            <h2 id="brand-title" class="heading mt-3 text-3xl leading-[1.15] sm:text-[2.25rem]">Merek yang kami hadirkan.</h2>
        </div>

        <div x-ref="track" class="mt-10 grid grid-flow-col grid-rows-2 justify-center gap-3.5 overflow-x-auto scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden auto-cols-[calc(50%-7px)] sm:auto-cols-[calc(33.333%-9.5px)] lg:auto-cols-[calc(16.666%-11.7px)]" data-reveal style="--d: 100ms">
            @foreach($partners as $p)
                @php $logo = '<img src="' . e(asset('storage/' . $p->logo)) . '" alt="' . e($p->name) . '" loading="lazy" class="max-h-16 max-w-[75%] object-contain opacity-70 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">'; @endphp
                @if($p->link)
                    <a href="{{ $p->link }}" target="_blank" rel="noopener noreferrer" class="group flex h-32 min-w-[180px] items-center justify-center rounded-xl border border-stone-200 bg-white p-4 transition hover:border-orange-500">{!! $logo !!}</a>
                @else
                    <div class="group flex h-32 min-w-[180px] items-center justify-center rounded-xl border border-stone-200 bg-white p-4">{!! $logo !!}</div>
                @endif
            @endforeach
        </div>

        @if($partners->count() > 6)
            <div class="mt-6 flex justify-center gap-3">
                <button type="button" @click="scroll(-1)" class="flex h-10 w-10 items-center justify-center rounded-full border border-stone-200 bg-white hover:border-orange-500" aria-label="Sebelumnya">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <button type="button" @click="scroll(1)" class="flex h-10 w-10 items-center justify-center rounded-full border border-stone-200 bg-white hover:border-orange-500" aria-label="Berikutnya">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                </button>
            </div>
        @endif
    </section>
@endif

{{-- ============ KATA PELANGGAN ============ --}}
@if($testimonials->isNotEmpty())
    <section class="container-x pt-24" aria-labelledby="testi-title">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="eyebrow">Testimoni</p>
            <h2 id="testi-title" class="heading mt-3 text-3xl leading-[1.15] sm:text-[2.25rem]">Apa kata pelanggan kami.</h2>
        </div>
        <div class="mt-10 flex flex-wrap justify-center gap-5">
            @foreach($testimonials as $t)
                <figure class="flex w-full flex-col rounded-2xl border border-stone-200 bg-white p-8 md:w-[calc(50%-10px)] lg:w-[calc(33.333%-14px)]" data-reveal style="--d: {{ ($loop->index % 3) * 80 }}ms">
                    <div class="flex gap-1 text-amber-500" role="img" aria-label="Rating {{ $t->rating }} dari 5">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="h-5 w-5 {{ $i <= $t->rating ? '' : 'opacity-25' }}" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3.8l2.5 5.1 5.6.8-4.1 4 1 5.6-5-2.7-5 2.7 1-5.6-4.1-4 5.6-.8L12 3.8z"/></svg>
                        @endfor
                    </div>
                    <blockquote class="mt-5 flex-1 text-base leading-relaxed text-stone-700">“{{ $t->content }}”</blockquote>
                    <figcaption class="mt-6 flex items-center gap-3 border-t border-stone-100 pt-5">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-stone-200 text-sm font-semibold text-stone-600">{{ strtoupper(\Illuminate\Support\Str::substr($t->customer_name, 0, 2)) }}</span>
                        <span class="text-sm font-semibold text-stone-900">{{ $t->customer_name }}</span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif

{{-- ============ ARTIKEL ============ --}}
@if($articles->isNotEmpty())
    <section class="container-x pt-24" aria-labelledby="artikel-title">
        <div class="flex flex-wrap items-end justify-between gap-5" data-reveal>
            <div class="max-w-xl">
                <p class="eyebrow">Insight & pengetahuan</p>
                <h2 id="artikel-title" class="heading mt-3 text-3xl leading-[1.15] [text-wrap:balance] sm:text-[2.25rem]">Wawasan untuk usaha kopi Anda.</h2>
                <p class="mt-3 text-[15px] text-stone-600">Panduan memilih peralatan, perawatan, dan tren seputar bisnis kopi.</p>
            </div>
            <a href="{{ route('articles.index') }}" class="btn btn-outline btn-sm">Lihat Semua Artikel →</a>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($articles as $a)
                <div data-reveal style="--d: {{ $loop->index * 90 }}ms" class="h-full"><x-article-card :article="$a" /></div>
            @endforeach
        </div>
    </section>
@endif

{{-- ============ AJAKAN ============ --}}
<section class="container-x pb-4 pt-24">
    <div class="flex flex-col items-start justify-between gap-6 rounded-2xl bg-brand p-8 text-white sm:flex-row sm:items-center sm:p-12" data-reveal>
        <div class="max-w-xl">
            <h2 class="heading text-2xl [text-wrap:balance] sm:text-3xl">Siap melihat peralatan yang sesuai untuk usaha Anda?</h2>
            <p class="mt-2 text-sm text-white/80">Jelajahi katalog lengkap dengan harga dan stok yang jelas, atau konsultasikan kebutuhan Anda lebih dulu.</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-3">
            <a href="{{ route('products.index') }}" class="btn bg-white text-brand hover:bg-orange-100">Buka Katalog</a>
            <a href="{{ route('contact.index') }}" class="btn btn-ghost-light">Konsultasi Gratis</a>
        </div>
    </div>
</section>
@endsection
