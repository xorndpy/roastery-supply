@props(['product'])
@php
    $image = $product->images->first();
    $soldOut = ($product->stock ?? 0) <= 0;
@endphp

<div class="group flex flex-col overflow-hidden rounded-xl border border-stone-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-orange-500 hover:shadow-lg hover:shadow-stone-900/5">
    <a href="{{ route('products.show', $product->slug) }}" class="relative block aspect-square overflow-hidden bg-stone-100">
        @if($image)
            <img src="{{ asset('storage/' . $image->image) }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105 {{ $soldOut ? 'opacity-60' : '' }}">
        @else
            <div class="flex h-full w-full items-center justify-center text-xs tracking-wide text-stone-400">Foto produk</div>
        @endif
        <span class="absolute bottom-3 left-3 rounded border border-stone-200 bg-white px-2 py-0.5 text-[11px] {{ $soldOut ? 'text-red-700' : 'text-stone-600' }}">
            {{ $soldOut ? 'Stok habis' : 'Stok tersedia' }}
        </span>
    </a>

    <div class="flex flex-1 flex-col p-4">
        <span class="text-[11px] uppercase tracking-[0.1em] text-stone-500">{{ $product->brand->name ?? '' }}</span>
        <a href="{{ route('products.show', $product->slug) }}" class="mt-1 line-clamp-2 text-[15px] font-medium text-stone-900 transition hover:text-orange-600">{{ $product->name }}</a>
        <div class="mt-2 font-semibold text-orange-500">Rp {{ number_format($product->price, 0, ',', '.') }}</div>

        <form method="POST" action="{{ route('customer.cart.store') }}" class="mt-auto pt-4">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="hidden" name="quantity" value="1">
            <button type="submit" @disabled($soldOut) class="btn btn-outline btn-sm w-full">
                {{ $soldOut ? 'Stok Habis' : 'Tambah ke Keranjang' }}
            </button>
        </form>
    </div>
</div>
