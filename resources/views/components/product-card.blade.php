@props(['product'])
@php
    $image = $product->images->first();
    $soldOut = ($product->stock ?? 0) <= 0;
    $low = !$soldOut && $product->stock <= ($product->stock_threshold ?? 5);

    // Tampilan foto diatur dari Admin > Pengaturan > Tampilan (berlaku global).
    $ratio = ['portrait' => 'aspect-[4/5]', 'square' => 'aspect-square', 'landscape' => 'aspect-[4/3]'][$site['card_ratio'] ?? 'portrait'] ?? 'aspect-[4/5]';
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
        @if($product->is_featured)
            <span class="absolute left-3 top-3 rounded-sm bg-brand px-2 py-0.5 text-[11px] font-medium text-white">Unggulan</span>
        @endif
    </a>

    <div class="flex flex-1 flex-col border-t border-stone-100 p-4 sm:p-5">
        @if($product->brand)
            <span class="text-xs text-stone-500">{{ $product->brand->name }}</span>
        @endif
        <a href="{{ route('products.show', $product->slug) }}" class="mt-1 line-clamp-2 text-[15px] font-medium leading-snug text-stone-900 transition hover:text-brand">{{ $product->name }}</a>

        <div class="mt-3 font-semibold text-stone-900">Rp {{ number_format($product->price, 0, ',', '.') }}</div>
        <p class="mt-1 flex items-center gap-1.5 text-xs {{ $soldOut ? 'text-red-700' : ($low ? 'text-amber-700' : 'text-stone-500') }}">
            <span class="h-1.5 w-1.5 rounded-full {{ $soldOut ? 'bg-red-600' : ($low ? 'bg-amber-600' : 'bg-emerald-700') }}" aria-hidden="true"></span>
            {{ $soldOut ? 'Stok habis' : ($low ? 'Sisa ' . $product->stock : 'Stok tersedia') }}
        </p>

        <form method="POST" action="{{ route('customer.cart.store') }}" class="mt-auto pt-5">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" @disabled($soldOut) class="btn btn-outline btn-sm w-full hover:!bg-brand hover:!text-white">
                {{ $soldOut ? 'Stok Habis' : 'Tambah ke Keranjang' }}
            </button>
        </form>
    </div>
</div>
