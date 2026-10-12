@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<x-page-header title="Checkout" :crumbs="[['Beranda', route('home')], ['Checkout']]" />
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg">
            {{ session('warning') }}
        </div>
    @endif

    <form method="POST" action="{{ route('customer.checkout.store') }}"
          x-data="checkoutForm(@js(['shippingUrl' => route('customer.checkout.shipping'), 'cityId' => old('city_id', $customer->city_id ?? null), 'methodId' => old('shipping_method_id'), 'base' => (int) $afterDiscount, 'configured' => $shippingConfigured]))"
          @city-changed="onCity($event.detail)">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

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

                    <div class="mt-4">
                        <x-region-select :province-id="old('province_id', $customer->province_id ?? null)" :city-id="old('city_id', $customer->city_id ?? null)" />
                    </div>

                    <div class="mt-4 md:w-1/2">
                        <label class="block text-sm font-medium mb-2">Kode Pos *</label>
                        <input type="text" name="postal_code" inputmode="numeric" maxlength="5" pattern="[0-9]{5}"
                               value="{{ old('postal_code', $customer->postal_code ?? '') }}" required
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        @error('postal_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium mb-2">Catatan (opsional)</label>
                        <textarea name="note" rows="2"
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg border border-stone-200">
                    <h2 class="text-xl font-bold mb-4">Layanan Pengiriman</h2>

                    <template x-if="!configured">
                        <p class="text-sm text-stone-600 bg-stone-50 border border-stone-200 rounded-lg p-4">
                            Ongkos kirim akan dikonfirmasi oleh tim kami setelah pesanan dibuat.
                        </p>
                    </template>

                    <template x-if="configured">
                        <div>
                            <p x-show="!cityId" class="text-sm text-stone-500">Pilih provinsi dan kota tujuan untuk melihat ongkos kirim.</p>
                            <p x-show="cityId && loading" class="text-sm text-stone-500">Menghitung ongkos kirim…</p>
                            <p x-show="cityId && !loading && message" x-text="message" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3"></p>

                            <div class="space-y-3" x-show="methods.length > 0">
                                <template x-for="m in methods" :key="m.id">
                                    <label class="flex items-start gap-3 border rounded-lg p-4 cursor-pointer"
                                           :class="String(methodId) === String(m.id) ? 'border-orange-500 bg-orange-50' : 'border-stone-200'">
                                        <input type="radio" name="shipping_method_id" :value="m.id" x-model="methodId" class="mt-1">
                                        <span class="flex-1">
                                            <span class="font-medium" x-text="m.name"></span>
                                            <span class="block text-xs text-stone-500" x-show="m.etd" x-text="'Estimasi ' + m.etd"></span>
                                            <span class="block text-xs text-stone-500" x-show="m.description" x-text="m.description"></span>
                                        </span>
                                        <span class="font-medium" x-text="m.free ? 'Gratis' : rp(m.cost)"></span>
                                    </label>
                                </template>
                            </div>
                            @error('shipping_method_id') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                        </div>
                    </template>
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

                    {{-- Voucher (input memakai atribut form= karena berada di dalam form checkout) --}}
                    <div class="border-t border-stone-200 pt-4 mb-4">
                        @if($voucher)
                            <div class="flex items-center justify-between text-sm bg-green-50 border border-green-200 text-green-800 rounded-lg px-3 py-2">
                                <span>Voucher <strong>{{ $voucher->code }}</strong></span>
                                <button type="submit" form="voucher-remove-form" class="text-xs underline">Lepas</button>
                            </div>
                        @else
                            <label class="block text-sm font-medium mb-1.5" for="voucher-code">Kode voucher</label>
                            <div class="flex gap-2">
                                <input id="voucher-code" type="text" name="code" form="voucher-form" maxlength="50" autocomplete="off"
                                       value="{{ old('code') }}" placeholder="Masukkan kode"
                                       class="flex-1 min-w-0 border border-stone-300 rounded-lg px-3 py-2 text-sm uppercase">
                                <button type="submit" form="voucher-form"
                                        class="border border-stone-300 hover:bg-stone-50 px-4 py-2 rounded-lg text-sm">Pakai</button>
                            </div>
                            @if(session('voucher_error'))<p class="text-red-600 text-xs mt-1.5">{{ session('voucher_error') }}</p>@endif
                            @error('code')<p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>@enderror
                        @endif
                    </div>

                    <div class="border-t border-stone-200 pt-4 mb-6 space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-stone-600">Subtotal</span><span>Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                        @if($discount > 0)
                            <div class="flex justify-between text-green-700"><span>Diskon</span><span>− Rp {{ number_format($discount, 0, ',', '.') }}</span></div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-stone-600">Ongkir</span>
                            <span x-text="!configured ? 'Dikonfirmasi admin' : (selected ? (selected.free ? 'Gratis' : rp(selected.cost)) : '-')"></span>
                        </div>
                        <div class="flex justify-between text-base pt-2">
                            <span class="font-bold">Total</span>
                            <span class="font-bold text-orange-500" x-text="rp(total)">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-lg font-medium transition">
                        Buat Pesanan
                    </button>
                </div>
            </div>
        </div>
    </form>

    <form id="voucher-form" method="POST" action="{{ route('customer.checkout.voucher') }}">@csrf</form>
    <form id="voucher-remove-form" method="POST" action="{{ route('customer.checkout.voucher.remove') }}">@csrf @method('DELETE')</form>
</div>
<script>
    window.checkoutForm = function (cfg) {
        return {
            cityId: cfg.cityId || '',
            methodId: cfg.methodId || '',
            methods: [],
            loading: false,
            message: '',
            configured: cfg.configured,
            base: cfg.base,
            get selected() { return this.methods.find(m => String(m.id) === String(this.methodId)) || null; },
            get total() { return this.base + (this.selected ? this.selected.cost : 0); },
            rp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); },
            onCity(id) { this.cityId = id || ''; this.loadOptions(); },
            async loadOptions() {
                if (!this.configured) return;
                if (!this.cityId) { this.methods = []; this.methodId = ''; this.message = ''; return; }
                this.loading = true; this.message = '';
                try {
                    const res = await fetch(cfg.shippingUrl + '?city_id=' + encodeURIComponent(this.cityId), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.methods = data.options || [];
                    this.message = this.methods.length ? '' : (data.message || 'Layanan pengiriman belum tersedia.');
                    if (!this.selected) this.methodId = this.methods.length ? this.methods[0].id : '';
                } catch (e) {
                    this.methods = []; this.methodId = '';
                    this.message = 'Gagal memuat ongkos kirim. Coba pilih ulang kota.';
                }
                this.loading = false;
            },
        };
    };
</script>
@endsection
