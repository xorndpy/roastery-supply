@extends('layouts.app')

@section('title', $site['about_title'])

@section('content')
@php
    $paras = fn (?string $t) => array_values(array_filter(preg_split('/\R{2,}/', trim((string) $t))));
    $heroImg = !empty($site['about_header_bg']) ? asset('storage/' . $site['about_header_bg']) : null;
    $photo = !empty($site['about_photo']) ? asset('storage/' . $site['about_photo']) : null;

    // Nilai kerja. Ubah sesuai kenyataan usaha Anda; jangan menambahkan klaim yang belum bisa dibuktikan.
    $values = [
        ['Produk resmi', 'Kami hanya menjual produk dari distributor resmi dengan garansi, sehingga keaslian dan perlindungan purna jual terjamin.'],
        ['Rekomendasi yang sesuai', 'Saran kami mengikuti kapasitas dan anggaran usaha Anda, bukan sekadar spesifikasi tertinggi.'],
        ['Dukungan purna jual', 'Teknisi dan suku cadang tersedia agar gangguan peralatan dapat ditangani dengan cepat dan operasional kedai tetap berjalan.'],
        ['Pengiriman yang hati-hati', 'Setiap unit dikemas rapi dan diasuransikan selama pengiriman, karena peralatan berharga perlu tiba dalam kondisi baik.'],
        ['Pendampingan operasional', 'Instalasi di lokasi dan pelatihan dasar bagi tim barista membantu peralatan langsung digunakan secara optimal.'],
        ['Komunikasi yang jelas', 'Tim kami siap menjawab pertanyaan melalui WhatsApp dan email pada jam operasional, dengan informasi yang lugas.'],
    ];
@endphp

{{-- ============ HERO ============ --}}
<section class="relative overflow-hidden bg-[#1a0f0a] text-white">
    {{-- Visual: foto bila ada, kalau tidak pola biji kopi --}}
    <div class="absolute inset-0 lg:inset-y-0 lg:left-auto lg:right-0 lg:w-[60%]">
        @if($heroImg)
            <img src="{{ $heroImg }}" alt="" class="h-full w-full object-cover" fetchpriority="high">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-[#2b1a10] to-[#4a2f21]"></div>
            <div class="auth-beans absolute inset-0 opacity-90"></div>
            <div class="absolute -right-24 top-1/4 h-96 w-96 rounded-full bg-orange-500/20 blur-3xl"></div>
        @endif
    </div>
    <div class="absolute inset-0 bg-[#1a0f0a]/80 lg:hidden"></div>
    <div class="absolute inset-0 hidden bg-gradient-to-r from-[#1a0f0a] from-[40%] via-[#1a0f0a]/55 via-[62%] to-transparent lg:block"></div>
    <div class="absolute inset-x-0 bottom-0 h-1/4 bg-gradient-to-t from-[#1a0f0a]/70 to-transparent"></div>

    <div class="container-x relative py-16 sm:py-20 lg:py-28">
        <div class="max-w-[34rem]">
            <p class="eyebrow auth-rise !text-orange-300">{{ $site['about_title'] }}</p>
            <h1 class="heading auth-rise mt-4 text-4xl leading-[1.1] sm:text-[3.25rem] [text-wrap:balance]" style="--d:70ms">{{ $site['about_headline'] }}</h1>
            <p class="auth-rise mt-6 text-base leading-relaxed text-stone-100 sm:text-[17px]" style="--d:140ms">{{ $site['about_lead'] }}</p>
            <div class="auth-rise mt-8 flex flex-wrap gap-3" style="--d:210ms">
                <a href="{{ route('products.index') }}" class="btn btn-light">Lihat Katalog</a>
                <a href="{{ route('contact.index') }}" class="btn btn-ghost-light">Hubungi Kami</a>
            </div>
        </div>
    </div>
</section>

{{-- ============ PROFIL ============ --}}
<section class="container-x py-16 sm:py-24" aria-labelledby="profil-title">
    <div class="grid gap-10 lg:grid-cols-[5fr_7fr] lg:gap-20">
        <div data-reveal>
            <p class="eyebrow">Profil usaha</p>
            <h2 id="profil-title" class="heading mt-3 text-3xl leading-[1.15] sm:text-[2.25rem] [text-wrap:balance]">Fokus pada peralatan kopi profesional.</h2>
            @if($photo)
                <img src="{{ $photo }}" alt="" loading="lazy" class="mt-8 aspect-[4/3] w-full rounded-xl object-cover">
            @endif
        </div>
        <div class="space-y-5 text-[17px] leading-[1.8] text-stone-600" data-reveal style="--d: 100ms">
            @foreach($paras($site['about_body']) as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ NILAI ============ --}}
<section class="border-y border-stone-200 bg-stone-100/70 py-16 sm:py-24" aria-labelledby="nilai-title">
    <div class="container-x">
        <div class="max-w-xl" data-reveal>
            <p class="eyebrow">Nilai kerja</p>
            <h2 id="nilai-title" class="heading mt-3 text-3xl leading-[1.15] sm:text-[2.25rem] [text-wrap:balance]">Cara kami bekerja bersama pelanggan.</h2>
        </div>
        <ol class="mt-12 grid gap-x-12 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($values as $i => [$title, $desc])
                <li class="val-item" data-reveal style="--d: {{ ($i % 3) * 90 }}ms">
                    <span class="val-no" aria-hidden="true">0{{ $i + 1 }}</span>
                    <h3 class="val-title">{{ $title }}</h3>
                    <p class="val-desc">{{ $desc }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ============ MEREK & REKANAN (dari Admin > Partner) ============ --}}
@if($partners->isNotEmpty())
    <section class="container-x py-16 sm:py-24" aria-labelledby="brand-title">
        <div class="text-center" data-reveal>
            <p class="eyebrow">Merek &amp; rekanan</p>
            <h2 id="brand-title" class="heading mt-3 text-3xl leading-[1.15] sm:text-[2.25rem] [text-wrap:balance]">Merek yang kami layani.</h2>
        </div>
        <div class="mx-auto mt-10 grid max-w-4xl grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-4" data-reveal style="--d: 100ms">
            @foreach($partners as $p)
                @php $logo = '<img src="' . e(asset('storage/' . $p->logo)) . '" alt="' . e($p->name) . '" loading="lazy" class="max-h-12 max-w-[130px] object-contain opacity-60 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">'; @endphp
                @if($p->link)
                    <a href="{{ $p->link }}" target="_blank" rel="noopener noreferrer" class="group flex h-28 items-center justify-center rounded-xl border border-stone-200 bg-white p-3 transition hover:border-orange-500">{!! $logo !!}</a>
                @else
                    <div class="group flex h-28 items-center justify-center rounded-xl border border-stone-200 bg-white p-3">{!! $logo !!}</div>
                @endif
            @endforeach
        </div>
    </section>
@endif

{{-- ============ CTA ============ --}}
<section class="container-x {{ $partners->isNotEmpty() ? '' : 'pt-16 sm:pt-24' }} pb-4">
    <div class="flex flex-col items-start justify-between gap-6 rounded-2xl bg-brand p-8 text-white sm:flex-row sm:items-center sm:p-10" data-reveal>
        <div>
            <h2 class="heading text-2xl sm:text-3xl">Diskusikan kebutuhan kedai Anda.</h2>
            <p class="mt-1 max-w-md text-sm text-white/80">Tim kami siap membantu memilih peralatan yang sesuai dengan kapasitas dan anggaran usaha Anda.</p>
        </div>
        <a href="{{ route('contact.index') }}" class="btn shrink-0 bg-white text-brand hover:bg-orange-100">Hubungi Kami</a>
    </div>
</section>
@endsection
