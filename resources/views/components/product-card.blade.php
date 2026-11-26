@props(['product'])
@php
    $image = $product->images->first();
    $soldOut = ($product->stock ?? 0) <= 0;
    $fi = $product->flashItem();
    $low = !$soldOut && $product->stock <= ($product->stock_threshold ?? 5);

    // Tampilan foto diatur dari Admin > Pengaturan > Tampilan (berlaku global).
    // Di HP rasio potret dipadatkan jadi persegi supaya kartu tidak terlalu tinggi; mulai tablet ikut pengaturan.
    $ratio = ['portrait' => 'aspect-square sm:aspect-[4/5]', 'square' => 'aspect-square', 'landscape' => 'aspect-[4/3]'][$site['card_ratio'] ?? 'portrait'] ?? 'aspect-square sm:aspect-[4/5]';
    $cover = ($site['card_fit'] ?? 'contain') === 'cover';
@endphp

<div class="group flex h-full flex-col overflow-hidden rounded-lg border border-stone-200 bg-white transition-colors duration-200 hover:border-stone-400">
    <a href="{{ route('products.show', $product->slug) }}" class="relative block {{ $ratio }} overflow-hidden bg-white">
        @if($image)
            <img src="{{ thumb_url($image->image) }}" alt="{{ $product->name }}" loading="lazy" decoding="async" width="480" height="480"
                 onerror="this.onerror=null;this.src='{{ media_url($image->image) }}'"
                 class="h-full w-full {{ $cover ? 'object-cover' : 'object-contain p-2 sm:p-3' }} {{ $soldOut ? 'opacity-50' : '' }}">
        @else
            <div class="flex h-full w-full items-center justify-center bg-stone-100 text-xs text-stone-400">Foto produk</div>
        @endif
        @if($fi || $product->is_featured)
            <div class="absolute left-2 top-2 flex flex-col items-start gap-1 sm:left-3 sm:top-3">
                @if($fi)<span class="rounded-full bg-copper-500 px-2 py-0.5 text-[11px] font-bold text-white">-{{ $fi->discountPercent() }}%</span>@endif
                @if($product->is_featured)<span class="rounded-full bg-brand px-2 py-0.5 text-[11px] font-medium text-white">Unggulan</span>@endif
            </div>
        @endif
    </a>

    <div class="flex flex-1 flex-col border-t border-stone-100 p-3 sm:p-5">
        @if($product->brand)
            <span class="truncate text-xs text-stone-500">{{ $product->brand->name }}</span>
        @endif
        <a href="{{ route('products.show', $product->slug) }}" title="{{ $product->name }}" class="mt-1 line-clamp-1 text-[13px] font-medium leading-[1.3] text-stone-900 transition hover:text-brand sm:line-clamp-2 sm:text-[15px] sm:leading-snug">{{ $product->short_name }}</a>

        @if($fi)
            <div class="mt-2 flex flex-wrap items-baseline gap-x-2 sm:mt-3"><span class="font-semibold text-copper-700">Rp {{ number_format($fi->sale_price, 0, ',', '.') }}</span><span class="text-xs text-stone-400 line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</span></div>
        @else
        <div class="mt-2 text-[15px] font-semibold text-stone-900 sm:mt-3 sm:text-base">Rp {{ number_format($product->price, 0, ',', '.') }}</div>
        @endif
        <p class="mt-1 flex items-center gap-1.5 text-xs {{ $soldOut ? 'text-red-700' : ($low ? 'text-amber-700' : 'text-stone-500') }}">
            <span class="h-1.5 w-1.5 rounded-full {{ $soldOut ? 'bg-red-600' : ($low ? 'bg-amber-600' : 'bg-emerald-700') }}" aria-hidden="true"></span>
            {{ $soldOut ? 'Stok habis' : ($low ? 'Sisa ' . $product->stock : 'Stok tersedia') }}
        </p>

        @php $inCompare = in_array($product->id, array_map('intval', (array) session('compare', [])), true); @endphp
        <label x-data="{ on: {{ $inCompare ? 'true' : 'false' }}, busy: false, err: '',
                async flip() {
                    if (this.busy) return; this.busy = true; this.err = '';
                    try {
                        const r = await fetch(@js(route('compare.toggle', $product->slug)), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok) { this.on = j.selected; window.dispatchEvent(new CustomEvent('compare:changed', { detail: { count: j.count } })); }
                        else { this.err = j.message || 'Gagal.'; }
                    } catch (e) { this.err = 'Koneksi bermasalah.'; }
                    this.busy = false;
                } }"
               class="mt-3 flex cursor-pointer items-center gap-2 text-xs text-stone-600 select-none">
            <input type="checkbox" class="h-4 w-4 rounded border-stone-300" :checked="on" @click.prevent="flip()" aria-label="Bandingkan {{ $product->name }}">
            <span>Bandingkan</span>
            <span x-show="err" x-text="err" x-cloak class="text-red-700"></span>
        </label>

        <form method="POST" action="{{ route('customer.cart.store') }}" class="mt-auto pt-3 sm:pt-5">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" @disabled($soldOut) class="btn btn-outline btn-sm w-full gap-1.5 whitespace-nowrap !px-2 max-sm:!h-11 hover:!bg-brand hover:!text-white sm:!px-4">
                @if($soldOut)
                    Stok Habis
                @else
                    <svg class="h-4 w-4 shrink-0 sm:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span class="sm:hidden">Keranjang</span>
                    <span class="hidden sm:inline">Tambah ke Keranjang</span>
                    <span class="sr-only sm:hidden"> — tambah {{ $product->name }} ke keranjang</span>
                @endif
            </button>
        </form>
    </div>
</div>
