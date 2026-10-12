@extends('layouts.app')

@section('title', $product->name)

@section('content')
@php
    $imgs = $product->images->map(fn ($i) => asset('storage/' . $i->image))->values();
    $soldOut = $product->stock <= 0;
    $wa = !empty($site['contact_whatsapp']) ? preg_replace('/\D/', '', $site['contact_whatsapp']) : null;
@endphp

<div class="border-b border-stone-200 bg-stone-100">
    <div class="container-x py-4 text-[13px] text-stone-500">
        <a href="{{ route('home') }}" class="hover:text-stone-900">Beranda</a> <span class="mx-1">/</span>
        <a href="{{ route('products.index') }}" class="hover:text-stone-900">Katalog</a>
        @if($product->category)
            <span class="mx-1">/</span>
            <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" class="hover:text-stone-900">{{ $product->category->name }}</a>
        @endif
    </div>
</div>

<div class="container-x py-10">
    <div class="grid gap-12 lg:grid-cols-[1.05fr_1fr]"
         x-data='{ view: @json($product->model_3d ? "3d" : "img"), img: @json($imgs->first()), qty: 1, max: {{ max($product->stock, 1) }} }'>

        {{-- Galeri --}}
        <div>
            <div class="aspect-square overflow-hidden rounded-xl border border-stone-200 bg-stone-100">
                @if($product->model_3d)
                    <model-viewer x-show="view === '3d'" src="{{ asset('storage/' . $product->model_3d) }}" alt="{{ $product->name }}"
                                  auto-rotate camera-controls shadow-intensity="1" style="width:100%;height:100%"></model-viewer>
                @endif
                <template x-if="view === 'img' && img">
                    <img :src="img" alt="{{ $product->name }}" class="h-full w-full object-cover">
                </template>
                @if(!$product->model_3d && $imgs->isEmpty())
                    <div class="flex h-full items-center justify-center text-sm text-stone-400">Foto produk</div>
                @endif
            </div>

            @if($product->model_3d || $imgs->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-2.5">
                    @if($product->model_3d)
                        <button type="button" @click="view = '3d'"
                                class="flex aspect-square items-center justify-center rounded-lg border bg-stone-100 text-xs font-medium"
                                :class="view === '3d' ? 'border-orange-500' : 'border-stone-200'">3D</button>
                    @endif
                    @foreach($imgs as $src)
                        <button type="button" @click="view = 'img'; img = '{{ $src }}'"
                                class="aspect-square overflow-hidden rounded-lg border bg-stone-100"
                                :class="view === 'img' && img === '{{ $src }}' ? 'border-orange-500' : 'border-stone-200'">
                            <img src="{{ $src }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div>
            <span class="text-xs uppercase tracking-[0.1em] text-stone-500">{{ $product->brand->name ?? '' }}</span>
            <h1 class="heading mt-1.5 text-3xl leading-tight sm:text-4xl">{{ $product->name }}</h1>
            <div class="mt-3 text-3xl font-semibold text-orange-500">Rp {{ number_format($product->price, 0, ',', '.') }}</div>

            <div class="mt-2 text-sm {{ $soldOut ? 'text-red-700' : 'text-stone-500' }}">
                {{ $soldOut ? 'Stok habis' : 'Stok tersedia (' . $product->stock . ')' }}@if($product->sku) · SKU {{ $product->sku }}@endif
            </div>

            @if($product->description)
                <div class="mt-5 max-w-lg text-[15px] leading-relaxed text-stone-600">{!! nl2br(e($product->description)) !!}</div>
            @endif

            <form method="POST" action="{{ route('customer.cart.store') }}" class="mt-7">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" :value="qty">

                <div class="flex gap-3">
                    <div class="flex h-11 items-center rounded-md border border-stone-300 bg-white">
                        <button type="button" @click="qty = Math.max(1, qty - 1)" class="h-full w-10 text-lg hover:text-orange-500" aria-label="Kurangi">−</button>
                        <span class="min-w-[2rem] text-center text-sm font-medium" x-text="qty"></span>
                        <button type="button" @click="qty = Math.min(max, qty + 1)" class="h-full w-10 text-lg hover:text-orange-500" aria-label="Tambah">+</button>
                    </div>
                    <button type="submit" @disabled($soldOut) class="btn btn-primary flex-1">
                        {{ $soldOut ? 'Stok Habis' : 'Tambah ke Keranjang' }}
                    </button>
                </div>
            </form>

            @if($wa)
                <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Halo, saya ingin bertanya tentang ' . $product->name) }}" target="_blank" rel="noopener noreferrer"
                   class="btn btn-outline mt-3 w-full">Tanya via WhatsApp</a>
            @endif

            <dl class="mt-8 divide-y divide-stone-200 border-y border-stone-200 text-sm">
                @if($product->brand)<div class="flex py-3"><dt class="w-40 text-stone-500">Merek</dt><dd>{{ $product->brand->name }}</dd></div>@endif
                @if($product->category)<div class="flex py-3"><dt class="w-40 text-stone-500">Kategori</dt><dd>{{ $product->category->name }}</dd></div>@endif
                <div class="flex py-3"><dt class="w-40 text-stone-500">Garansi</dt><dd>Garansi resmi</dd></div>
                <div class="flex py-3"><dt class="w-40 text-stone-500">Pengiriman</dt><dd>Dikemas aman dan diasuransikan</dd></div>
            </dl>
        </div>
    </div>

    @if($relatedProducts->count())
        <section class="mt-16">
            <h2 class="heading mb-6 text-2xl">Produk Terkait</h2>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
                @foreach($relatedProducts as $related)
                    <x-product-card :product="$related" />
                @endforeach
            </div>
        </section>
    @endif
</div>

<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
@endsection
