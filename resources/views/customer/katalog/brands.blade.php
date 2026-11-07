@extends('layouts.shop')

@section('title', 'Merek')

@section('content')
<section class="border-b border-stone-200 bg-white">
    <div class="container-x py-8 sm:py-12">
        <p class="eyebrow">Merek</p>
        <h1 class="heading mt-2 text-3xl sm:text-4xl">Merek yang kami hadirkan.</h1>
        <p class="mt-2 max-w-xl text-sm text-stone-600">Pilih merek untuk melihat semua produknya.</p>
    </div>
</section>

<div class="container-x py-10" x-data="{ q: '' }">
    @if($brands->isNotEmpty())
        <input x-model="q" type="search" placeholder="Cari merek…" class="field mb-6 max-w-sm" aria-label="Cari merek">

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 lg:grid-cols-6">
            @foreach($brands as $b)
                <a href="{{ route('products.index', ['brand' => $b->slug]) }}"
                   x-show="@js(\Illuminate\Support\Str::lower($b->name)).includes(q.toLowerCase())"
                   class="group flex aspect-[4/3] flex-col items-center justify-center gap-2 rounded-2xl border border-stone-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-orange-500 hover:shadow-lg hover:shadow-stone-900/5">
                    @if($b->logo)
                        <img src="{{ asset('storage/' . $b->logo) }}" alt="{{ $b->name }}" loading="lazy" class="max-h-14 max-w-[80%] object-contain opacity-70 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">
                    @else
                        <span class="heading text-center text-lg leading-tight text-stone-800">{{ $b->name }}</span>
                    @endif
                    <span class="text-[11px] text-stone-500">{{ $b->products_count }} produk</span>
                </a>
            @endforeach
        </div>
    @else
        <p class="rounded-xl border border-stone-200 bg-white py-16 text-center text-stone-500">Belum ada merek.</p>
    @endif
</div>
@endsection
