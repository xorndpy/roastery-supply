@extends('layouts.app')

@section('title', 'Keranjang Belanja')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <h1 class="text-4xl font-bold mb-8">Keranjang Belanja</h1>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    @if(count($cart) > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            {{-- Cart Items --}}
            <div class="lg:col-span-2 space-y-4">
                @foreach($cart as $id => $item)
                    <div class="bg-white p-4 rounded-lg border border-stone-200 flex gap-4">
                        <div class="w-24 h-24 bg-stone-100 rounded overflow-hidden flex-shrink-0">
                            @if($item['image'])
                                <img src="{{ asset('storage/' . $item['image']) }}"
                                     alt="{{ $item['name'] }}"
                                     class="w-full h-full object-cover">
                            @endif
                        </div>

                        <div class="flex-1">
                            <a href="{{ route('products.show', $item['slug']) }}"
                               class="font-medium text-stone-900 hover:text-orange-500">
                                {{ $item['name'] }}
                            </a>
                            <div class="text-orange-500 font-bold mt-1">
                                Rp {{ number_format($item['price'], 0, ',', '.') }}
                            </div>

                            <div class="flex items-center gap-4 mt-3">
                                <form method="POST" action="{{ route('customer.cart.update', $id) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="quantity" value="{{ $item['quantity'] }}" 
                                           min="1" max="{{ $item['stock'] }}"
                                           class="w-16 border border-stone-300 rounded px-2 py-1 text-sm text-center">
                                    <button type="submit" class="text-xs text-stone-600 hover:text-orange-500">
                                        Update
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('customer.cart.destroy', $id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-sm text-stone-500">Subtotal</div>
                            <div class="font-bold text-stone-900">
                                Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Summary --}}
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg border border-stone-200 sticky top-24">
                    <h2 class="text-xl font-bold mb-4">Ringkasan</h2>

                    <div class="flex justify-between mb-2 text-sm">
                        <span class="text-stone-600">Subtotal</span>
                        <span class="font-medium">Rp {{ number_format($total, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between mb-4 text-sm">
                        <span class="text-stone-600">Ongkir</span>
                        <span class="font-medium">Dihitung saat checkout</span>
                    </div>

                    <div class="border-t border-stone-200 pt-4 mb-6 flex justify-between">
                        <span class="font-bold">Total</span>
                        <span class="font-bold text-orange-500">
                            Rp {{ number_format($total, 0, ',', '.') }}
                        </span>
                    </div>

                    <a href="{{ route('customer.checkout.index') }}"
                       class="block w-full bg-orange-500 hover:bg-orange-600 text-white text-center py-3 rounded-lg font-medium transition">
                        Lanjut ke Checkout
                    </a>

                    <a href="{{ route('products.index') }}"
                       class="block text-center text-sm text-stone-500 hover:text-stone-700 mt-4">
                        Lanjut Belanja
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-16 bg-white rounded-lg border border-stone-200">
            <p class="text-stone-500 mb-4">Keranjang kamu masih kosong.</p>
            <a href="{{ route('products.index') }}"
               class="inline-block bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
                Mulai Belanja
            </a>
        </div>
    @endif
</div>
@endsection