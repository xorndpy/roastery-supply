@extends('layouts.shop')

@section('title', $product->name)

@section('content')
@php
    $imgs = $product->images->map(fn ($i) => media_url($i->image))->values();
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
         x-data='{ view: "img", img: @json($imgs->first()), qty: 1, msg: "", max: {{ max($product->stock, 1) }} }'>

        {{-- Galeri --}}
        <div>
            <div class="aspect-square overflow-hidden rounded-xl border border-stone-200 bg-stone-100">

                <template x-if="view === 'img' && img">
                    <img :src="img" alt="{{ $product->name }}" class="h-full w-full object-cover">
                </template>
                @if($imgs->isEmpty())
                    <div class="flex h-full items-center justify-center text-sm text-stone-400">Foto produk</div>
                @endif
            </div>

            @if($imgs->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-2.5">

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
                        <button type="button" @click="qty = Math.max(1, qty - 1); msg = ''" class="h-full w-10 text-lg hover:text-orange-500" aria-label="Kurangi">−</button>
                        <input type="text" inputmode="numeric" autocomplete="off" :value="qty" aria-label="Jumlah"
                               @change="let v = parseInt($event.target.value.replace(/\D/g, ''), 10); if (isNaN(v) || v < 1) v = 1; if (v > max) { v = max; msg = 'Stok tersedia hanya ' + max + ' unit.' } else { msg = '' } qty = v; $event.target.value = v"
                               class="h-full w-12 border-0 bg-transparent p-0 text-center text-sm font-medium focus:ring-0">
                        <button type="button" @click="if (qty >= max) { msg = 'Stok tersedia hanya ' + max + ' unit.' } else { qty++; msg = '' }" class="h-full w-10 text-lg hover:text-orange-500" aria-label="Tambah">+</button>
                    </div>
                    <button type="submit" @disabled($soldOut) class="btn btn-primary flex-1">
                        {{ $soldOut ? 'Stok Habis' : 'Tambah ke Keranjang' }}
                    </button>
                </div>
                <p x-show="msg" x-cloak x-text="msg" role="status" class="mt-2 text-[13px] text-red-600"></p>
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

@endsection
