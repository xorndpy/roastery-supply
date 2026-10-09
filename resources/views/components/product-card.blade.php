@props(['product'])
<a href="{{ route('products.show', $product->slug) }}"
   class="bg-white rounded-lg overflow-hidden border border-stone-200 hover:shadow-lg transition group">
    <div class="aspect-square bg-stone-100 overflow-hidden">
        @if($product->images->first())
            <img src="{{ asset('storage/' . $product->images->first()->image) }}" alt="{{ $product->name }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
        @else
            <div class="w-full h-full flex items-center justify-center text-stone-400 text-sm">No Image</div>
        @endif
    </div>
    <div class="p-4">
        <div class="text-xs text-stone-500 uppercase">{{ $product->brand->name ?? '' }}</div>
        <div class="font-medium text-stone-900 mt-1 line-clamp-2">{{ $product->name }}</div>
        <div class="text-orange-500 font-bold mt-2">Rp {{ number_format($product->price, 0, ',', '.') }}</div>
    </div>
</a>
