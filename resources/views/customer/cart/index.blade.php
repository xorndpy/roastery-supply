@extends('layouts.app')

@section('title', 'Keranjang Belanja')

@section('content')
<x-page-header plain title="Keranjang" :crumbs="[['Beranda', route('home')], ['Keranjang']]" />

<div class="container-x py-10">
    @if(session('success'))<div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
    @if(session('warning'))<div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{{ session('warning') }}</div>@endif

    @if(count($cart) > 0)
        <div class="grid items-start gap-10 lg:grid-cols-[1fr_340px]">

            <div class="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
                @foreach($cart as $id => $item)
                    <div class="flex gap-4 p-5">
                        <a href="{{ route('products.show', $item['slug']) }}" class="h-24 w-24 shrink-0 overflow-hidden rounded-lg border border-stone-200 bg-stone-100">
                            @if($item['image'])
                                <img src="{{ asset('storage/' . $item['image']) }}" alt="{{ $item['name'] }}" class="h-full w-full object-cover">
                            @endif
                        </a>

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('products.show', $item['slug']) }}" class="font-medium hover:text-orange-600">{{ $item['name'] }}</a>
                            <div class="mt-0.5 text-sm text-stone-500">Rp {{ number_format($item['price'], 0, ',', '.') }}</div>

                            <div class="mt-3 flex flex-wrap items-center gap-4">
                                <form method="POST" action="{{ route('customer.cart.update', $id) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input type="number" name="quantity" value="{{ $item['quantity'] }}" min="1" max="{{ $item['stock'] }}"
                                           class="h-9 w-16 rounded-md border-stone-300 text-center text-sm focus:border-orange-500 focus:ring-orange-500" aria-label="Jumlah">
                                    <button type="submit" class="text-[13px] text-stone-500 hover:text-stone-900">Perbarui</button>
                                </form>
                                <form method="POST" action="{{ route('customer.cart.destroy', $id) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[13px] text-stone-400 hover:text-red-600">Hapus</button>
                                </form>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-xs text-stone-500">Subtotal</div>
                            <div class="font-semibold">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="rounded-xl border border-stone-200 bg-white p-6 lg:sticky lg:top-24">
                <h2 class="heading mb-4 text-lg">Ringkasan Pesanan</h2>
                <div class="flex justify-between py-1.5 text-sm text-stone-500"><span>Subtotal</span><span>Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                <div class="flex justify-between py-1.5 text-sm text-stone-500"><span>Ongkos kirim</span><span>Dihitung saat checkout</span></div>
                <div class="mt-3 flex justify-between border-t border-stone-200 pt-4 text-[17px] font-semibold">
                    <span>Total</span><span class="text-orange-500">Rp {{ number_format($total, 0, ',', '.') }}</span>
                </div>
                <a href="{{ route('customer.checkout.index') }}" class="btn btn-primary mt-5 w-full">Lanjut ke Checkout</a>
                <a href="{{ route('products.index') }}" class="mt-4 block text-center text-sm text-orange-600 hover:underline">Lanjut belanja</a>
            </aside>
        </div>
    @else
        <div class="rounded-xl border border-stone-200 bg-white px-6 py-20 text-center">
            <h2 class="heading text-2xl">Keranjang masih kosong</h2>
            <p class="mt-1 text-stone-500">Jelajahi katalog untuk menemukan produk.</p>
            <a href="{{ route('products.index') }}" class="btn btn-primary mt-6">Lihat Katalog</a>
        </div>
    @endif
</div>
@endsection
