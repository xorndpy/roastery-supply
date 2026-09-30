@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <h1 class="text-4xl font-bold mb-8">Checkout</h1>

    <form method="POST" action="{{ route('customer.checkout.store') }}">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            {{-- Form Alamat --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-lg border border-stone-200">
                    <h2 class="text-xl font-bold mb-4">Alamat Pengiriman</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Nama Lengkap *</label>
                            <input type="text" name="name" 
                                   value="{{ old('name', $customer->name ?? auth()->user()->name) }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">No. Telepon *</label>
                            <input type="text" name="phone" 
                                   value="{{ old('phone', $customer->phone ?? '') }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium mb-2">Alamat Lengkap *</label>
                        <textarea name="address" rows="3" required
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">{{ old('address', $customer->address ?? '') }}</textarea>
                        @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Kota *</label>
                            <input type="text" name="city" 
                                   value="{{ old('city', $customer->city ?? '') }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Provinsi *</label>
                            <input type="text" name="province" 
                                   value="{{ old('province', $customer->province ?? '') }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Kode Pos *</label>
                            <input type="text" name="postal_code" 
                                   value="{{ old('postal_code', $customer->postal_code ?? '') }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium mb-2">Catatan (opsional)</label>
                        <textarea name="note" rows="2"
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg border border-stone-200">
                    <h2 class="text-xl font-bold mb-4">Metode Pembayaran</h2>
                    <div class="bg-orange-50 border border-orange-200 p-4 rounded-lg">
                        <div class="font-medium text-orange-900">Transfer Manual</div>
                        <p class="text-sm text-orange-700 mt-1">
                            Setelah pesanan dibuat, kamu akan menerima instruksi transfer. 
                            Upload bukti bayar di halaman pesanan.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-lg border border-stone-200 sticky top-24">
                    <h2 class="text-xl font-bold mb-4">Ringkasan Pesanan</h2>

                    <div class="space-y-3 mb-4">
                        @foreach($cart as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-stone-600">
                                    {{ $item['name'] }} <span class="text-stone-400">×{{ $item['quantity'] }}</span>
                                </span>
                                <span class="font-medium">
                                    Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-stone-200 pt-4 mb-6 flex justify-between">
                        <span class="font-bold">Total</span>
                        <span class="font-bold text-orange-500">
                            Rp {{ number_format($total, 0, ',', '.') }}
                        </span>
                    </div>

                    <button type="submit"
                            class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-lg font-medium transition">
                        Buat Pesanan
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection