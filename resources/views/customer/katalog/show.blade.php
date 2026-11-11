@extends('layouts.shop')

@section('title', $product->name)

@php
    $imgs = $product->images->map(fn ($i) => media_url($i->image))->values();
    $soldOut = $product->stock <= 0;
    $wa = !empty($site['contact_whatsapp']) ? preg_replace('/\D/', '', $site['contact_whatsapp']) : null;
    $banner = !empty($site['header_bg_katalog']) ? media_url($site['header_bg_katalog']) : null;
    $price = (float) $product->price;

    // Tiap varian menunjuk ke indeks foto galeri (atau null bila tanpa foto khusus).
    $variants = $product->variants->map(function ($v) use ($product) {
        $idx = $v->product_image_id ? $product->images->search(fn ($i) => (int) $i->id === (int) $v->product_image_id) : false;
        return ['id' => (int) $v->id, 'type' => $v->type, 'name' => $v->name, 'idx' => $idx === false ? null : (int) $idx];
    })->values();

    $state = [
        'imgs' => $imgs->all(), 'idx' => $variants->first()['idx'] ?? 0, 'qty' => 1, 'max' => max($product->stock, 1),
        'variants' => $variants, 'vid' => $variants->first()['id'] ?? null,
        'quote' => false, 'msg' => '',
        'pid' => $product->id, 'pname' => $product->name, 'price' => $price,
    ];

    $ld = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name,
        'sku' => $product->sku, 'description' => \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 300, ''),
        'image' => $imgs->all() ?: null,
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => ['@type' => 'Offer', 'priceCurrency' => 'IDR', 'price' => (string) (int) $price,
            'availability' => $soldOut ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock', 'url' => url()->current()],
        'aggregateRating' => $reviewCount ? ['@type' => 'AggregateRating', 'ratingValue' => $reviewAvg, 'reviewCount' => $reviewCount] : null,
    ]);
@endphp

@push('head')
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $product->name }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 150, '') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if($imgs->first())<meta property="og:image" content="{{ $imgs->first() }}">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
{{-- Banner --}}
<div class="relative flex h-40 items-center justify-center overflow-hidden bg-stone-800 sm:h-56">
    @if($banner)<img src="{{ $banner }}" alt="" class="absolute inset-0 h-full w-full object-cover">@endif
    <div class="absolute inset-0 bg-black/45"></div>
    <div class="relative text-2xl font-extrabold uppercase tracking-[0.2em] text-white sm:text-4xl" style="text-shadow:0 2px 8px rgba(0,0,0,.45)">Detail Product</div>
</div>

<div class="container-x py-8 sm:py-10">
    @if(session('success'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <div class="grid gap-6 md:grid-cols-[1fr_1.15fr] lg:gap-10"
         x-data="{
            ...@js($state),
            get img() { return this.imgs[this.idx] || null },
            go(d) { if (this.imgs.length) this.idx = (this.idx + d + this.imgs.length) % this.imgs.length },
            pick(v) { this.vid = v.id; if (v.idx !== null) this.idx = v.idx },
         }"
         x-init="window.track && window.track('view_item', { id: pid, name: pname, value: price })">

        {{-- Galeri --}}
        <div class="md:sticky md:top-24 md:self-start">
            <div class="relative aspect-[16/11] overflow-hidden rounded-2xl bg-white shadow-md ring-1 ring-stone-100">
                <template x-if="img"><img :src="img" alt="{{ $product->name }}" class="h-full w-full object-contain p-4"></template>
                @if($imgs->isEmpty())<div class="flex h-full items-center justify-center text-sm text-stone-400">Foto produk</div>@endif

                @if($imgs->count() > 1)
                    <button type="button" @click="go(-1)" class="absolute left-3 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow ring-1 ring-stone-200 hover:bg-stone-50" aria-label="Foto sebelumnya">
                        <svg class="h-4 w-4 text-stone-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button type="button" @click="go(1)" class="absolute right-3 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-white shadow ring-1 ring-stone-200 hover:bg-stone-50" aria-label="Foto berikutnya">
                        <svg class="h-4 w-4 text-stone-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                @endif
            </div>

            @if($imgs->count() > 1)
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($imgs as $n => $src)
                        <button type="button" @click="idx = {{ $n }}" class="h-12 w-12 overflow-hidden rounded-lg border-2 bg-white transition"
                                :class="idx === {{ $n }} ? 'border-orange-500' : 'border-stone-200 hover:border-stone-300'">
                            <img src="{{ $src }}" alt="" class="h-full w-full object-contain p-0.5">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wide text-stone-500">{{ $product->brand->name ?? '' }}</div>
            <h1 class="mt-1 font-sans text-2xl font-extrabold leading-tight text-slate-900 sm:text-3xl">{{ $product->name }}</h1>

            @if($reviewCount)
                <a href="#ulasan" class="mt-1.5 inline-flex items-center gap-1.5 text-sm text-stone-600">
                    <span class="text-amber-500">★</span><b>{{ number_format($reviewAvg, 1, ',', '.') }}</b><span class="text-stone-400">({{ $reviewCount }} ulasan)</span>
                </a>
            @endif

            <div class="mt-3 rounded-xl border border-orange-100 bg-gradient-to-r from-white to-orange-50 px-4 py-3 shadow-sm">
                <div class="text-2xl font-extrabold text-slate-900 sm:text-3xl">Rp {{ number_format($price, 0, ',', '.') }}</div>
                <div class="mt-0.5 text-[12px] {{ $soldOut ? 'text-red-700' : 'text-stone-400' }}">
                    {{ $soldOut ? 'Stok habis' : 'Stok tersedia (' . $product->stock . ')' }}@if($product->sku) · SKU {{ $product->sku }}@endif
                </div>
            </div>

            @if($product->description || !empty($product->specs))
                <div class="mt-4 rounded-xl border border-stone-200 bg-white p-4 text-[12.5px] leading-relaxed text-slate-500 shadow-sm">
                    @if($product->description)<div>{!! nl2br(e($product->description)) !!}</div>@endif
                    @if(!empty($product->specs))
                        <div class="{{ $product->description ? 'mt-4' : '' }}">
                            @foreach($product->specs as $row)<div>{{ $row['key'] ?? '' }} : {{ $row['value'] ?? '' }}</div>@endforeach
                        </div>
                    @endif
                </div>
            @endif

            <form id="cart-form" method="POST" action="{{ route('customer.cart.store') }}" class="mt-4"
                  @submit="window.track && window.track('add_to_cart', { id: pid, name: pname, value: price, qty: qty })">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" :value="qty">
                <input type="hidden" name="variant_id" :value="vid">

                <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                    @if($variants->isNotEmpty())
                        <div class="mb-4">
                            <div class="mb-2 text-xs font-bold text-slate-900">Varian {{ $variants->first()['type'] }}</div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="v in variants" :key="v.id">
                                    <button type="button" @click="pick(v)" class="rounded-full border px-4 py-1 text-xs transition"
                                            :class="vid === v.id ? 'border-orange-500 bg-orange-500 text-white' : 'border-stone-300 bg-white text-slate-700 hover:border-stone-400'" x-text="v.name"></button>
                                </template>
                            </div>
                        </div>
                    @endif

                    <div class="text-xs font-bold text-slate-900">Quantity</div>
                    <div class="mt-2 flex items-center gap-2">
                        <button type="button" @click="qty = Math.max(1, qty - 1); msg = ''" class="flex h-8 w-8 items-center justify-center rounded-full border border-stone-200 bg-white shadow hover:text-orange-500" aria-label="Kurangi">−</button>
                        <input type="text" inputmode="numeric" autocomplete="off" :value="qty" aria-label="Jumlah"
                               @change="let v = parseInt($event.target.value.replace(/\D/g, ''), 10); if (isNaN(v) || v < 1) v = 1; if (v > max) { v = max; msg = 'Stok tersedia hanya ' + max + ' unit.' } else { msg = '' } qty = v; $event.target.value = v"
                               class="h-8 w-24 rounded border border-stone-300 bg-white p-0 text-center text-xs focus:border-orange-400 focus:ring-0">
                        <button type="button" @click="if (qty >= max) { msg = 'Stok tersedia hanya ' + max + ' unit.' } else { qty++; msg = '' }" class="flex h-8 w-8 items-center justify-center rounded-full border border-stone-200 bg-white shadow hover:text-orange-500" aria-label="Tambah">+</button>
                    </div>
                    <p x-show="msg" x-cloak x-text="msg" role="status" class="mt-2 text-[13px] text-red-600"></p>
                </div>

                <button type="submit" @disabled($soldOut) class="mt-4 w-full rounded-xl bg-slate-900 py-3.5 text-sm font-bold text-white shadow-lg transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ $soldOut ? 'Stok Habis' : 'Tambah ke Keranjang' }}
                </button>
            </form>

            <div class="mt-3 grid {{ $wa ? 'grid-cols-2' : 'grid-cols-1' }} gap-3">
                <button type="button" @click="quote = !quote" class="rounded-xl border border-stone-300 bg-white py-2.5 text-sm font-semibold text-slate-700 hover:bg-stone-50">Minta Penawaran</button>
                @if($wa)
                    <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Halo, saya ingin bertanya tentang ' . $product->name) }}" target="_blank" rel="noopener noreferrer"
                       class="rounded-xl border border-stone-300 bg-white py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-stone-50">WhatsApp</a>
                @endif
            </div>

            <form x-show="quote" x-cloak method="POST" action="{{ route('products.quote', $product->slug) }}" class="mt-3 space-y-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm"
                  @submit="window.track && window.track('generate_lead', { id: pid, name: pname, value: price })">
                @csrf
                <div class="text-sm font-bold text-slate-900">Minta penawaran</div>
                <input type="hidden" name="variant" :value="(variants.find(v => v.id === vid) || {}).name || ''">
                <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
                <input type="text" name="name" value="{{ old('name', auth()->user()->name ?? '') }}" required maxlength="100" placeholder="Nama / usaha" class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email ?? '') }}" required maxlength="255" placeholder="Email" class="rounded-lg border border-stone-300 px-3 py-2.5 text-sm">
                    <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="20" placeholder="No. WhatsApp" class="rounded-lg border border-stone-300 px-3 py-2.5 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" name="quantity" :value="qty" min="1" max="999" required class="rounded-lg border border-stone-300 px-3 py-2.5 text-sm">
                    <select name="payment" class="rounded-lg border border-stone-300 px-3 py-2.5 text-sm"><option value="tunai">Tunai</option><option value="cicilan">Cicilan / leasing</option></select>
                </div>
                <textarea name="note" rows="2" maxlength="1000" placeholder="Catatan (opsional)" class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm"></textarea>
                @if($errors->any())<p class="text-[13px] text-red-600">{{ $errors->first() }}</p>@endif
                <button type="submit" class="w-full rounded-xl bg-slate-900 py-3 text-sm font-bold text-white hover:bg-slate-800">Kirim Permintaan</button>
            </form>
        </div>
    </div>

    {{-- Ulasan --}}
    <section id="ulasan" class="mt-14 max-w-3xl scroll-mt-24">
        <div class="mb-5 flex items-end justify-between">
            <h2 class="text-xl font-extrabold text-slate-900">Ulasan Pembeli</h2>
            @if($reviewCount)<div class="text-sm text-stone-600"><span class="text-amber-500">★</span> <b>{{ number_format($reviewAvg, 1, ',', '.') }}</b> dari {{ $reviewCount }} ulasan</div>@endif
        </div>

        @if($canReview)
            <form method="POST" action="{{ route('products.review', $product->slug) }}" class="mb-6 space-y-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm" x-data="{ r: 5 }">
                @csrf
                <div class="text-sm font-bold text-slate-900">Tulis ulasan</div>
                <input type="hidden" name="rating" :value="r">
                <div class="flex gap-1 text-2xl">
                    @foreach(range(1, 5) as $n)
                        <button type="button" @click="r = {{ $n }}" :class="r >= {{ $n }} ? 'text-amber-500' : 'text-stone-300'" aria-label="{{ $n }} bintang">★</button>
                    @endforeach
                </div>
                <textarea name="content" rows="3" required minlength="10" maxlength="1000" placeholder="Ceritakan pengalaman memakai produk ini (min. 10 karakter)" class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm">{{ old('content') }}</textarea>
                @error('content')<p class="text-[13px] text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Kirim Ulasan</button>
            </form>
        @endif

        @forelse($reviews as $rv)
            <article class="border-b border-stone-200 py-5">
                <div class="flex items-center justify-between text-sm">
                    <div class="font-medium">{{ \Illuminate\Support\Str::before($rv->user->name ?? 'Pembeli', ' ') }} <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-normal text-emerald-700">Pembeli terverifikasi</span></div>
                    <time class="text-xs text-stone-400">{{ $rv->created_at->translatedFormat('d M Y') }}</time>
                </div>
                <div class="mt-1 text-amber-500" aria-label="{{ $rv->rating }} dari 5">{{ str_repeat('★', $rv->rating) }}<span class="text-stone-300">{{ str_repeat('★', 5 - $rv->rating) }}</span></div>
                <p class="mt-2 text-[14px] leading-relaxed text-slate-500">{!! nl2br(e($rv->content)) !!}</p>
            </article>
        @empty
            <p class="text-sm text-stone-500">Belum ada ulasan. Ulasan bisa ditulis pembeli setelah pesanan selesai.</p>
        @endforelse
    </section>

    @if($relatedProducts->count())
        <section class="mt-16">
            <h2 class="mb-6 text-xl font-extrabold text-slate-900">Produk Terkait</h2>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
                @foreach($relatedProducts as $related)<x-product-card :product="$related" />@endforeach
            </div>
        </section>
    @endif
</div>
@endsection
