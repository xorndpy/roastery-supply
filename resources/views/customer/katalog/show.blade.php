@extends('layouts.shop')

@section('title', $product->name)

@php
    $imgs = $product->images->map(fn ($i) => media_url($i->image))->values();
    $soldOut = $product->stock <= 0;
    $low = !$soldOut && $product->stock <= ($product->stock_threshold ?? 5);
    $wa = !empty($site['contact_whatsapp']) ? preg_replace('/\D/', '', $site['contact_whatsapp']) : null;
    $basePrice = (float) $product->price;
    $rate = is_numeric($site['installment_rate'] ?? null) ? (float) $site['installment_rate'] : 1.5;

    $variants = $product->variants->map(fn ($v) => [
        'id' => $v->id, 'type' => $v->type, 'name' => $v->name, 'color' => $v->color_hex, 'adjust' => (float) $v->price_adjust,
    ])->values();

    // Spesifikasi: pakai kolom `specs`; bila kosong, fallback parse baris "Kunci : Nilai" dari deskripsi.
    $lines = collect(preg_split('/\r?\n/', (string) $product->description))->map(fn ($l) => trim($l))->filter();
    $isSpec = fn ($l) => preg_match('/^[^:]{2,25}:\s*\S+/', $l) === 1;
    if (!empty($product->specs)) {
        $specs = collect($product->specs)->map(fn ($r) => [$r['key'] ?? '', $r['value'] ?? ''])->values();
        $about = $lines->implode("\n");
    } else {
        $specs = $lines->filter($isSpec)->map(fn ($l) => array_map('trim', explode(':', $l, 2)))->values();
        $about = $lines->reject($isSpec)->implode("\n");
    }

    $state = [
        'img' => $imgs->first(), 'qty' => 1, 'max' => max($product->stock, 1), 'base' => $basePrice, 'rate' => $rate,
        'variants' => $variants, 'vid' => optional($variants->first())['id'] ?? null,
        'tenor' => 12, 'tab' => 'desc', 'open' => false, 'quote' => false, 'msg' => '',
        'pid' => $product->id, 'pname' => $product->name,
    ];

    $ld = array_filter([
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name,
        'sku' => $product->sku, 'description' => \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 300, ''),
        'image' => $imgs->all() ?: null,
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => ['@type' => 'Offer', 'priceCurrency' => 'IDR', 'price' => (string) (int) $basePrice,
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

<div class="container-x py-8 pb-28 lg:py-10 lg:pb-10">
    @if(session('success'))<div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <div class="grid gap-8 lg:grid-cols-[1.05fr_1fr] lg:gap-12"
         x-data="{
            ...@js($state),
            get variant() { return this.variants.find(v => v.id === this.vid) || null },
            get unit() { return this.base + (this.variant ? this.variant.adjust : 0) },
            get total() { return this.unit * this.qty },
            fmt(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID') },
            monthly(t) { return Math.ceil(this.total * (1 + this.rate / 100 * t) / t / 1000) * 1000 },
         }"
         x-init="window.track && window.track('view_item', { id: pid, name: pname, value: base })">

        {{-- Galeri --}}
        <div>
            <div class="relative aspect-square overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                <template x-if="img"><img :src="img" alt="{{ $product->name }}" class="h-full w-full object-contain p-3"></template>
                @if($imgs->isEmpty())<div class="flex h-full items-center justify-center text-sm text-stone-400">Foto produk</div>@endif
                @if($product->is_featured)<span class="absolute left-3 top-3 rounded-full bg-brand px-3 py-1 text-xs font-medium text-white">Unggulan</span>@endif
            </div>
            @if($imgs->count() > 1)
                <div class="mt-3 grid grid-cols-5 gap-2.5">
                    @foreach($imgs as $src)
                        <button type="button" @click="img = @js($src)" class="aspect-square overflow-hidden rounded-lg border-2 bg-white transition"
                                :class="img === @js($src) ? 'border-orange-500' : 'border-stone-200 hover:border-stone-300'">
                            <img src="{{ $src }}" alt="" class="h-full w-full object-contain p-1">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div>
            <span class="text-xs uppercase tracking-[0.1em] text-stone-500">{{ $product->brand->name ?? '' }}</span>
            <h1 class="heading mt-1.5 text-2xl leading-tight sm:text-4xl">{{ $product->name }}</h1>

            @if($reviewCount)
                <a href="#ulasan" class="mt-2 inline-flex items-center gap-1.5 text-sm text-stone-600">
                    <span class="text-amber-500">★</span><b>{{ number_format($reviewAvg, 1, ',', '.') }}</b>
                    <span class="text-stone-400">({{ $reviewCount }} ulasan)</span>
                </a>
            @endif

            <div class="mt-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm">
                <div class="text-3xl font-semibold text-orange-500" x-text="fmt(unit)">Rp {{ number_format($basePrice, 0, ',', '.') }}</div>
                <div class="mt-2 flex items-center gap-1.5 text-sm {{ $soldOut ? 'text-red-700' : ($low ? 'text-amber-700' : 'text-stone-600') }}">
                    <span class="h-2 w-2 rounded-full {{ $soldOut ? 'bg-red-600' : ($low ? 'bg-amber-600' : 'bg-emerald-600') }}"></span>
                    {{ $soldOut ? 'Stok habis' : ($low ? 'Sisa ' . $product->stock . ' unit' : 'Stok tersedia (' . $product->stock . ')') }}
                    @if($product->sku)<span class="text-stone-400">· SKU {{ $product->sku }}</span>@endif
                </div>
                @if(!$soldOut)
                    <div class="mt-3 border-t border-stone-100 pt-3 text-sm text-stone-600">
                        Cicilan mulai <b class="text-stone-900" x-text="fmt(monthly(24)) + '/bulan'"></b>
                        <span class="text-stone-400">· 24 bulan (estimasi)</span>
                    </div>
                @endif
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 text-[13px] text-stone-700">
                @foreach(['Garansi resmi', 'Barang original', 'Aman & diasuransikan', 'Bisa konsultasi dulu'] as $t)
                    <div class="flex items-center gap-2 rounded-lg bg-stone-50 px-3 py-2.5">
                        <span class="flex h-5 w-5 flex-none items-center justify-center rounded-full bg-orange-100 text-[11px] text-orange-600">✓</span>{{ $t }}
                    </div>
                @endforeach
            </div>

            {{-- Beli --}}
            <form id="cart-form" method="POST" action="{{ route('customer.cart.store') }}" class="mt-6"
                  @submit="window.track && window.track('add_to_cart', { id: pid, name: pname, value: unit, qty: qty })">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" :value="qty">
                <input type="hidden" name="variant_id" :value="vid">

                @if($variants->isNotEmpty())
                    <div class="mb-4">
                        <div class="mb-2 text-sm font-medium text-stone-700">{{ $variants->first()['type'] }}: <span class="font-normal text-stone-500" x-text="variant ? variant.name : ''"></span></div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="v in variants" :key="v.id">
                                <button type="button" @click="vid = v.id"
                                        class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                                        :class="vid === v.id ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-stone-300 bg-white text-stone-700 hover:border-stone-400'">
                                    <span x-show="v.color" class="h-4 w-4 rounded-full border border-stone-300" :style="`background:${v.color}`"></span>
                                    <span x-text="v.name"></span>
                                    <span x-show="v.adjust !== 0" class="text-xs text-stone-400" x-text="(v.adjust > 0 ? '+' : '−') + fmt(Math.abs(v.adjust))"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                @endif

                <div class="flex gap-3">
                    <div class="flex h-12 items-center rounded-md border border-stone-300 bg-white">
                        <button type="button" @click="qty = Math.max(1, qty - 1); msg = ''" class="h-full w-10 text-lg hover:text-orange-500" aria-label="Kurangi">−</button>
                        <input type="text" inputmode="numeric" autocomplete="off" :value="qty" aria-label="Jumlah"
                               @change="let v = parseInt($event.target.value.replace(/\D/g, ''), 10); if (isNaN(v) || v < 1) v = 1; if (v > max) { v = max; msg = 'Stok tersedia hanya ' + max + ' unit.' } else { msg = '' } qty = v; $event.target.value = v"
                               class="h-full w-12 border-0 bg-transparent p-0 text-center text-sm font-medium focus:ring-0">
                        <button type="button" @click="if (qty >= max) { msg = 'Stok tersedia hanya ' + max + ' unit.' } else { qty++; msg = '' }" class="h-full w-10 text-lg hover:text-orange-500" aria-label="Tambah">+</button>
                    </div>
                    <button type="submit" @disabled($soldOut) class="btn btn-primary h-12 flex-1">{{ $soldOut ? 'Stok Habis' : 'Tambah ke Keranjang' }}</button>
                </div>
                <p x-show="msg" x-cloak x-text="msg" role="status" class="mt-2 text-[13px] text-red-600"></p>
            </form>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <button type="button" @click="quote = !quote" class="btn btn-outline h-12">Minta Penawaran</button>
                @if($wa)
                    <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Halo, saya ingin bertanya tentang ' . $product->name) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline h-12">WhatsApp</a>
                @endif
            </div>

            {{-- Simulasi cicilan --}}
            @if(!$soldOut)
                <div class="mt-4 rounded-xl border border-stone-200 bg-white p-4">
                    <div class="mb-3 text-sm font-medium">Simulasi cicilan</div>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach([3, 6, 12, 24] as $t)
                            <button type="button" @click="tenor = {{ $t }}" class="rounded-lg border py-2 text-sm transition"
                                    :class="tenor === {{ $t }} ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-stone-300 hover:border-stone-400'">{{ $t }} bln</button>
                        @endforeach
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <span class="text-sm text-stone-500">Per bulan</span>
                        <span class="text-xl font-semibold text-stone-900" x-text="fmt(monthly(tenor))"></span>
                    </div>
                    <p class="mt-2 text-[12px] leading-snug text-stone-400">Estimasi dengan bunga flat {{ rtrim(rtrim(number_format($rate, 2, ',', '.'), '0'), ',') }}%/bulan. Nilai akhir mengikuti persetujuan pihak leasing.</p>
                </div>
            @endif

            {{-- Form penawaran --}}
            <form x-show="quote" x-cloak method="POST" action="{{ route('products.quote', $product->slug) }}" class="mt-4 space-y-3 rounded-xl border border-stone-200 bg-white p-4"
                  @submit="window.track && window.track('generate_lead', { id: pid, name: pname, value: total })">
                @csrf
                <div class="text-sm font-medium">Minta penawaran</div>
                <input type="hidden" name="variant" :value="variant ? variant.type + ': ' + variant.name : ''">
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
                <button type="submit" class="btn btn-primary h-11 w-full">Kirim Permintaan</button>
            </form>

            {{-- Tab --}}
            <div class="mt-8">
                <div class="flex gap-6 overflow-x-auto border-b border-stone-200 text-sm font-medium">
                    @foreach(['desc' => 'Deskripsi', 'spec' => 'Spesifikasi', 'ship' => 'Garansi & Pengiriman'] as $k => $label)
                        @if($k !== 'spec' || $specs->count())
                            <button type="button" @click="tab = '{{ $k }}'" class="-mb-px whitespace-nowrap border-b-2 pb-3 transition"
                                    :class="tab === '{{ $k }}' ? 'border-orange-500 text-stone-900' : 'border-transparent text-stone-500 hover:text-stone-800'">{{ $label }}</button>
                        @endif
                    @endforeach
                </div>

                <div x-show="tab === 'desc'" class="pt-5">
                    @if($about !== '')
                        <div class="max-w-xl text-[15px] leading-relaxed text-stone-600" :class="open ? '' : 'line-clamp-5'">{!! nl2br(e($about)) !!}</div>
                        @if(mb_strlen($about) > 280)
                            <button type="button" @click="open = !open" class="mt-2 text-sm font-medium text-orange-600 hover:underline" x-text="open ? 'Sembunyikan' : 'Lihat selengkapnya'"></button>
                        @endif
                    @else
                        <p class="text-sm text-stone-500">Belum ada deskripsi.</p>
                    @endif
                </div>

                @if($specs->count())
                    <div x-show="tab === 'spec'" x-cloak class="pt-5">
                        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-stone-200 bg-stone-200 text-sm">
                            @foreach($specs as [$k, $v])
                                <div class="bg-white p-3.5"><dt class="text-xs text-stone-500">{{ $k }}</dt><dd class="mt-0.5 font-medium text-stone-900">{{ $v }}</dd></div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                <div x-show="tab === 'ship'" x-cloak class="pt-5">
                    <dl class="divide-y divide-stone-200 border-y border-stone-200 text-sm">
                        @if($product->brand)<div class="flex py-3"><dt class="w-40 text-stone-500">Merek</dt><dd>{{ $product->brand->name }}</dd></div>@endif
                        @if($product->category)<div class="flex py-3"><dt class="w-40 text-stone-500">Kategori</dt><dd>{{ $product->category->name }}</dd></div>@endif
                        @if($product->weight)<div class="flex py-3"><dt class="w-40 text-stone-500">Berat kirim</dt><dd>{{ number_format($product->weight / 1000, 1, ',', '.') }} kg</dd></div>@endif
                        <div class="flex py-3"><dt class="w-40 text-stone-500">Garansi</dt><dd>Garansi resmi</dd></div>
                        <div class="flex py-3"><dt class="w-40 text-stone-500">Pengiriman</dt><dd>Dikemas aman dan diasuransikan</dd></div>
                    </dl>
                </div>
            </div>
        </div>

        {{-- Sticky bar mobile --}}
        <div class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-3 border-t border-stone-200 bg-white/95 p-3 backdrop-blur lg:hidden">
            <div class="min-w-0 flex-1">
                <div class="text-[11px] text-stone-500">Harga</div>
                <div class="truncate text-lg font-semibold text-orange-500" x-text="fmt(unit)"></div>
            </div>
            <button type="submit" form="cart-form" @disabled($soldOut) class="btn btn-primary h-11">{{ $soldOut ? 'Stok Habis' : 'Tambah ke Keranjang' }}</button>
        </div>
    </div>

    {{-- Ulasan --}}
    <section id="ulasan" class="mt-14 max-w-3xl scroll-mt-24">
        <div class="mb-5 flex items-end justify-between">
            <h2 class="heading text-2xl">Ulasan Pembeli</h2>
            @if($reviewCount)<div class="text-sm text-stone-600"><span class="text-amber-500">★</span> <b>{{ number_format($reviewAvg, 1, ',', '.') }}</b> dari {{ $reviewCount }} ulasan</div>@endif
        </div>

        @if($canReview)
            <form method="POST" action="{{ route('products.review', $product->slug) }}" class="mb-6 space-y-3 rounded-xl border border-stone-200 bg-white p-4" x-data="{ r: 5 }">
                @csrf
                <div class="text-sm font-medium">Tulis ulasan</div>
                <input type="hidden" name="rating" :value="r">
                <div class="flex gap-1 text-2xl">
                    @foreach(range(1, 5) as $n)
                        <button type="button" @click="r = {{ $n }}" :class="r >= {{ $n }} ? 'text-amber-500' : 'text-stone-300'" aria-label="{{ $n }} bintang">★</button>
                    @endforeach
                </div>
                <textarea name="content" rows="3" required minlength="10" maxlength="1000" placeholder="Ceritakan pengalaman memakai produk ini (min. 10 karakter)" class="w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm">{{ old('content') }}</textarea>
                @error('content')<p class="text-[13px] text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="btn btn-primary h-11">Kirim Ulasan</button>
            </form>
        @endif

        @forelse($reviews as $rv)
            <article class="border-b border-stone-200 py-5">
                <div class="flex items-center justify-between text-sm">
                    <div class="font-medium">{{ \Illuminate\Support\Str::before($rv->user->name ?? 'Pembeli', ' ') }} <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-normal text-emerald-700">Pembeli terverifikasi</span></div>
                    <time class="text-xs text-stone-400">{{ $rv->created_at->translatedFormat('d M Y') }}</time>
                </div>
                <div class="mt-1 text-amber-500" aria-label="{{ $rv->rating }} dari 5">{{ str_repeat('★', $rv->rating) }}<span class="text-stone-300">{{ str_repeat('★', 5 - $rv->rating) }}</span></div>
                <p class="mt-2 text-[15px] leading-relaxed text-stone-600">{!! nl2br(e($rv->content)) !!}</p>
            </article>
        @empty
            <p class="text-sm text-stone-500">Belum ada ulasan. Ulasan bisa ditulis pembeli setelah pesanan selesai.</p>
        @endforelse
    </section>

    @if($relatedProducts->count())
        <section class="mt-16">
            <h2 class="heading mb-6 text-2xl">Produk Terkait</h2>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-5">
                @foreach($relatedProducts as $related)<x-product-card :product="$related" />@endforeach
            </div>
        </section>
    @endif
</div>
@endsection
