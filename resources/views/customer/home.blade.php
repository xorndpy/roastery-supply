@extends('layouts.app')

@section('title', ($site['meta_title'] ?? null) ?: $site['site_name'] . ' — ' . $site['tagline'])

@section('content')

{{-- Hero: banner terjadwal bila ada, kalau tidak teks dari Pengaturan --}}
@if($banners->isNotEmpty())
    <section class="relative">
        <div class="flex overflow-x-auto snap-x snap-mandatory scroll-smooth">
            @foreach($banners as $b)
                @php $tag = $b->link ? 'a' : 'div'; @endphp
                <{{ $tag }} @if($b->link) href="{{ $b->link }}" @endif class="snap-center shrink-0 w-full block">
                    <img src="{{ asset('storage/' . $b->image) }}" alt="{{ $b->title }}" class="w-full h-64 sm:h-96 object-cover">
                </{{ $tag }}>
            @endforeach
        </div>
    </section>
@else
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h1 class="text-5xl font-bold mb-4">{{ $site['site_name'] }}</h1>
        <p class="text-lg text-stone-600 mb-8">{{ $site['tagline'] }}</p>
        <a href="{{ route('products.index') }}" class="bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600">Lihat Katalog</a>
    </section>
@endif

@if($categories->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-2xl font-bold mb-6">Kategori</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($categories as $c)
                <a href="{{ route('products.index', ['category' => $c->slug]) }}" class="bg-white border border-stone-200 rounded-lg p-4 text-center hover:border-orange-500">{{ $c->name }}</a>
            @endforeach
        </div>
    </section>
@endif

@if($featuredProducts->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex justify-between items-end mb-6">
            <h2 class="text-2xl font-bold">Produk Unggulan</h2>
            <a href="{{ route('products.index') }}" class="text-sm text-orange-600 hover:underline">Lihat semua →</a>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($featuredProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
@endif

@if($partners->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-sm uppercase tracking-wider text-stone-500 mb-6 text-center">Merek & Rekanan</h2>
        <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6">
            @foreach($partners as $p)
                @php $logo = '<img src="' . e(asset('storage/' . $p->logo)) . '" alt="' . e($p->name) . '" class="h-10 max-w-[140px] object-contain opacity-70 hover:opacity-100 transition">'; @endphp
                @if($p->link)
                    <a href="{{ $p->link }}" target="_blank" rel="noopener noreferrer">{!! $logo !!}</a>
                @else
                    {!! $logo !!}
                @endif
            @endforeach
        </div>
    </section>
@endif

@if($testimonials->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-2xl font-bold mb-6">Kata Pelanggan</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($testimonials as $t)
                <figure class="bg-white border border-stone-200 rounded-lg p-6">
                    <div class="text-amber-500 mb-2" aria-label="{{ $t->rating }} dari 5">{{ str_repeat('★', $t->rating) }}<span class="text-stone-300">{{ str_repeat('★', 5 - $t->rating) }}</span></div>
                    <blockquote class="text-stone-700 whitespace-pre-line">{{ $t->content }}</blockquote>
                    <figcaption class="mt-4 text-sm font-medium">{{ $t->customer_name }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif

@endsection
