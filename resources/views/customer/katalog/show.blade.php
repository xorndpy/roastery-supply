@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <nav class="text-sm text-stone-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-orange-500">Beranda</a>
        <span class="mx-2">/</span>
        <a href="{{ route('products.index') }}" class="hover:text-orange-500">Katalog</a>
        <span class="mx-2">/</span>
        <span class="text-stone-900">{{ $product->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        
        {{-- Gambar / 3D --}}
        <div>
            <div class="aspect-square bg-stone-100 rounded-lg overflow-hidden mb-4">
                @if($product->model_3d)
                    <model-viewer 
                        src="{{ asset('storage/' . $product->model_3d) }}"
                        alt="{{ $product->name }}"
                        auto-rotate
                        camera-controls
                        shadow-intensity="1"
                        style="width: 100%; height: 100%;">
                    </model-viewer>
                @elseif($product->images->first())
                    <img src="{{ asset('storage/' . $product->images->first()->image) }}"
                         alt="{{ $product->name }}"
                         class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-stone-400">
                        No Image
                    </div>
                @endif
            </div>

            @if($product->images->count() > 1)
                <div class="grid grid-cols-4 gap-2">
                    @foreach($product->images as $image)
                        <div class="aspect-square bg-stone-100 rounded overflow-hidden cursor-pointer">
                            <img src="{{ asset('storage/' . $image->image) }}"
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover hover:opacity-80">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div>
            <div class="text-sm text-stone-500 uppercase">{{ $product->brand->name ?? '' }}</div>
            <h1 class="text-3xl font-bold mt-2 mb-4">{{ $product->name }}</h1>
            
            <div class="text-3xl font-bold text-orange-500 mb-6">
                Rp {{ number_format($product->price, 0, ',', '.') }}
            </div>

            <div class="mb-6">
                <span class="text-sm text-stone-600">Stok: </span>
                <span class="font-medium {{ $product->stock > 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $product->stock > 0 ? $product->stock . ' tersedia' : 'Habis' }}
                </span>
            </div>

            <div class="prose prose-stone mb-8">
                {!! nl2br(e($product->description)) !!}
            </div>

            <form method="POST" action="{{ route('customer.cart.store') }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                
                <div class="flex items-center gap-4 mb-6">
                    <label class="text-sm font-medium">Jumlah:</label>
                    <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}"
                           class="w-20 border border-stone-300 rounded-lg px-3 py-2 text-center">
                </div>

                <button type="submit" 
                        {{ $product->stock <= 0 ? 'disabled' : '' }}
                        class="w-full bg-orange-500 hover:bg-orange-600 disabled:bg-stone-300 disabled:cursor-not-allowed
                               text-white py-3 rounded-lg font-medium transition">
                    Tambah ke Keranjang
                </button>
            </form>
        </div>
    </div>

    {{-- Related Products --}}
    @if($relatedProducts->count())
        <section class="mt-16">
            <h2 class="text-2xl font-bold mb-6">Produk Terkait</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($relatedProducts as $related)
                    <a href="{{ route('products.show', $related->slug) }}"
                       class="bg-white rounded-lg overflow-hidden border border-stone-200 hover:shadow-lg transition">
                        <div class="aspect-square bg-stone-100">
                            @if($related->images->first())
                                <img src="{{ asset('storage/' . $related->images->first()->image) }}"
                                     alt="{{ $related->name }}"
                                     class="w-full h-full object-cover">
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="font-medium line-clamp-2">{{ $related->name }}</div>
                            <div class="text-orange-500 font-bold mt-2">
                                Rp {{ number_format($related->price, 0, ',', '.') }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Model Viewer Script --}}
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
</div>
@endsection