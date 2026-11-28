@extends('layouts.shop')

@section('title', 'Checkout')

@section('content')
<x-page-header plain title="Checkout" :crumbs="[['Beranda', route('home')], ['Checkout']]" />
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 pb-28 lg:pb-12">

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg">
            {{ session('warning') }}
        </div>
    @endif

    <form method="POST" action="{{ route('customer.checkout.store') }}"
          x-data="checkoutForm(@js(['shippingUrl' => route('customer.checkout.shipping'), 'cityId' => old('city_id', $customer->city_id ?? null), 'methodId' => old('shipping_method_id'), 'base' => (int) $afterDiscount, 'configured' => $shippingConfigured, 'postal' => old('postal_code', $customer->postal_code ?? '')]))"
          @city-changed="onCity($event.detail)">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
            
            {{-- Form Alamat --}}
            <div class="min-w-0 lg:col-span-2 space-y-5 sm:space-y-6">
                <div class="bg-white p-4 sm:p-6 rounded-lg border border-stone-200">
                    <h2 class="text-xl font-bold mb-4">Alamat Pengiriman</h2>

                    @if($addresses->isNotEmpty())
                        @php
                            $addrJson = $addresses->map(fn ($a) => ['id' => $a->id, 'recipient_name' => $a->recipient_name, 'phone' => $a->phone, 'address' => $a->address, 'province_id' => $a->province_id, 'city_id' => $a->city_id, 'postal_code' => $a->postal_code])->values();
                        @endphp
                        <div x-data="{ list: @js($addrJson), sel: {{ $addresses->first(fn ($a) => old('address') !== null ? ($a->address === old('address') && (int) $a->city_id === (int) old('city_id')) : $a->is_default)?->id ?? 'null' }},
                                pick(id) {
                                    const a = this.list.find(x => x.id === id); if (!a) return; this.sel = id;
                                    const f = this.$el.closest('form');
                                    f.elements['name'].value = a.recipient_name; f.elements['phone'].value = a.phone; f.elements['address'].value = a.address;
                                    f.elements['postal_code'].value = a.postal_code || '';
                                    if (window.Alpine) Alpine.$data(f).postal = a.postal_code || '';
                                    window.dispatchEvent(new CustomEvent('address-selected', { detail: a }));
                                } }" class="mb-5">
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <p class="text-sm font-medium">Pilih dari buku alamat</p>
                                <a href="{{ route('customer.addresses.index') }}" class="text-xs text-stone-500 underline underline-offset-2 hover:text-stone-800">Kelola alamat</a>
                            </div>
                            <div class="grid gap-2">
                                @foreach($addresses as $a)
                                    <button type="button" @click="pick({{ $a->id }})" :class="sel === {{ $a->id }} ? 'border-brand ring-1 ring-brand' : 'border-stone-200 hover:border-stone-400'"
                                            class="flex w-full items-start gap-3 rounded-lg border bg-white p-3 text-left text-sm">
                                        <span class="mt-0.5 h-3.5 w-3.5 shrink-0 rounded-full border-2" :class="sel === {{ $a->id }} ? 'border-brand bg-brand' : 'border-stone-400'" aria-hidden="true"></span>
                                        <span class="min-w-0">
                                            <span class="block font-medium text-stone-900">{{ $a->label }} &middot; {{ $a->recipient_name }}@if($a->is_default) <span class="ml-1 rounded bg-stone-100 px-1.5 py-0.5 text-[11px] font-medium text-stone-600">Utama</span>@endif</span>
                                            <span class="block break-words text-stone-600">{{ $a->address }}, {{ $a->regionLine() }}</span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-stone-500">Anda juga bisa mengubah isian di bawah untuk mengirim ke alamat lain.</p>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Nama Lengkap *</label>
                            <input type="text" name="name" 
                                   value="{{ old('name', $customer->name ?? auth()->user()->name) }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">No. Telepon *</label>
                            <input type="text" name="phone" 
                                   value="{{ old('phone', $customer->phone ?? '') }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">
                            @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium mb-2">Alamat Lengkap *</label>
                        <textarea name="address" rows="3" required
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">{{ old('address', $customer->address ?? '') }}</textarea>
                        @error('address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-4">
                        <x-region-select :province-id="old('province_id', $customer->province_id ?? null)" :city-id="old('city_id', $customer->city_id ?? null)" />
                    </div>

                    <div class="mt-4 md:w-1/2">
                        <label class="block text-sm font-medium mb-2">Kode Pos *</label>
                        <input type="text" name="postal_code" @input="onPostal($event.target.value)" inputmode="numeric" maxlength="5" pattern="[0-9]{5}"
                               value="{{ old('postal_code', $customer->postal_code ?? '') }}" required
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">
                        @error('postal_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="mt-4">
                        <label class="block text-sm font-medium mb-2">Catatan (opsional)</label>
                        <textarea name="note" rows="2"
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">{{ old('note') }}</textarea>
                    </div>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg border border-stone-200">
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

                            <div class="space-y-6" x-show="methods.length > 0">
                                <template x-for="g in groups" :key="g.key">
                                    <div>
                                        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-3">
                                            <h3 class="text-sm font-semibold text-stone-900" x-text="g.label"></h3>
                                            <span class="text-xs text-stone-500" x-text="g.hint"></span>
                                        </div>
                                        <p class="mb-1.5 text-xs text-stone-500" x-show="g.items.length > 4" x-text="g.items.length + ' layanan · gulir untuk melihat lainnya'"></p>
                                        <div class="space-y-2" role="radiogroup" :aria-label="g.label" style="max-height: 19rem; overflow-y: auto; padding-right: .25rem;">
                                            <template x-for="m in g.items" :key="m.id">
                                                <label class="flex items-start gap-3 border rounded-lg p-3 cursor-pointer"
                                                       :class="String(methodId) === String(m.id) ? 'border-copper-500 bg-copper-50' : 'border-stone-200'">
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
                                    </div>
                                </template>
                            </div>
                            @error('shipping_method_id') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                        </div>
                    </template>
                </div>

                <div class="bg-white p-4 sm:p-6 rounded-lg border border-stone-200">
                    <h2 class="text-xl font-bold mb-4">Metode Pembayaran</h2>
                    @if(app(\App\Services\Payments\MidtransService::class)->configured())
                        <div class="bg-copper-50 border border-copper-200 p-4 rounded-lg">
                            <div class="font-medium text-copper-900">Pembayaran Online</div>
                            <p class="text-sm text-copper-700 mt-1">
                                Setelah pesanan dibuat, klik Bayar Sekarang untuk memilih Virtual Account bank, QRIS, e-wallet, atau kartu.
                                Status pesanan diperbarui otomatis setelah pembayaran berhasil.
                            </p>
                        </div>
                    @else
                    <div class="bg-copper-50 border border-copper-200 p-4 rounded-lg">
                        <div class="font-medium text-copper-900">Transfer Manual</div>
                        <p class="text-sm text-copper-700 mt-1">
                            Setelah pesanan dibuat, kamu akan menerima instruksi transfer. 
                            Upload bukti bayar di halaman pesanan.
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Summary --}}
            <div class="min-w-0 lg:col-span-1">
                <div class="bg-white p-4 sm:p-6 rounded-lg border border-stone-200 lg:sticky lg:top-24">
                    <h2 class="text-xl font-bold mb-4">Ringkasan Pesanan</h2>

                    <div class="space-y-3 mb-4">
                        @foreach($cart as $item)
                            <div class="flex items-start justify-between gap-3 text-sm">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words font-medium text-stone-800">{{ $item['name'] }}</p>
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $item['quantity'] }} &times; Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                                </div>
                                <span class="shrink-0 whitespace-nowrap font-medium">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</span>
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
                                        class="shrink-0 border border-stone-300 hover:bg-stone-50 px-4 py-2 rounded-lg text-sm">Pakai</button>
                            </div>
                            @if(session('voucher_error'))<p class="text-red-600 text-xs mt-1.5">{{ session('voucher_error') }}</p>@endif
                            @error('code')<p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>@enderror
                        @endif

                        @if($availableVouchers->isNotEmpty())
                            <div class="mt-4">
                                <p class="text-sm font-medium mb-2" id="voucher-list-title">Voucher untuk kamu</p>
                                <ul class="space-y-2" aria-labelledby="voucher-list-title">
                                    @foreach($availableVouchers as $v)
                                        <li class="flex items-center justify-between gap-3 rounded-lg border {{ $v['state'] === 'ready' ? 'border-stone-300' : 'border-stone-200 bg-stone-50' }} px-3 py-2.5">
                                            <div class="min-w-0 text-sm">
                                                <div class="font-medium">{{ $v['benefit'] }}@if($v['is_new_member']) <span class="ml-1 rounded bg-stone-100 px-1.5 py-0.5 text-xs font-normal text-stone-700">Member baru</span>@endif</div>
                                                <div class="text-xs text-stone-600">{{ implode(' · ', $v['terms']) }}</div>
                                                @if($v['state'] === 'min_purchase')
                                                    <div class="mt-0.5 text-xs font-medium text-stone-800">Belanja Rp {{ number_format($v['gap'], 0, ',', '.') }} lagi untuk memakai voucher ini.</div>
                                                @elseif($v['valid_until'])
                                                    <div class="text-xs text-stone-600">Berlaku sampai {{ $v['valid_until']->translatedFormat('d M Y') }}</div>
                                                @endif
                                            </div>
                                            @if($v['state'] === 'ready')
                                                <button type="submit" form="voucher-pick-form" name="code" value="{{ $v['code'] }}"
                                                        class="shrink-0 min-h-11 rounded-lg border border-stone-300 px-4 text-sm font-medium hover:bg-stone-50"
                                                        aria-label="Pakai voucher {{ $v['benefit'] }}, kode {{ $v['code'] }}">Pakai</button>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="border-t border-stone-200 pt-4 mb-6 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><span class="text-stone-600">Subtotal</span><span class="whitespace-nowrap">Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                        @if($discount > 0)
                            <div class="flex justify-between gap-3 text-green-700"><span>Diskon</span><span class="whitespace-nowrap">− Rp {{ number_format($discount, 0, ',', '.') }}</span></div>
                        @endif
                        <div class="flex justify-between gap-3">
                            <span class="text-stone-600">Ongkir</span>
                            <span class="text-right" x-text="!configured ? 'Dikonfirmasi admin' : (selected ? (selected.free ? 'Gratis' : rp(selected.cost)) : '-')"></span>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 text-base pt-2">
                            <span class="font-bold">Total</span>
                            <span class="whitespace-nowrap text-lg font-bold text-copper-500" x-text="rp(total)">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
                        </div>
                        @php
                            $ppnRate = app(\App\Services\ProfitLossService::class)->taxRate();
                            $ppnAmount = $ppnRate ? round(($total - $discount) * $ppnRate / (100 + $ppnRate), 2) : 0;
                        @endphp
                        @if($ppnRate)
                            <div class="flex justify-between gap-3 text-sm text-stone-600"><span>Sudah termasuk PPN {{ rtrim(rtrim(number_format($ppnRate, 2, ',', ''), '0'), ',') }}%</span><span class="whitespace-nowrap">Rp {{ number_format($ppnAmount, 0, ',', '.') }}</span></div>
                        @endif
                    </div>

                    <div class="mb-4">
                        <label for="terms" class="flex cursor-pointer items-start gap-2.5 text-sm text-stone-700">
                            <input id="terms" type="checkbox" name="terms" value="1" required @checked(old('terms'))
                                   class="mt-0.5 h-5 w-5 shrink-0 rounded border-stone-400 text-copper-500 focus:ring-copper-500">
                            <span>Saya menyetujui <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-medium text-copper-700 underline">Syarat &amp; Ketentuan</a>, termasuk ketentuan retur dan garansi.</span>
                        </label>
                        @error('terms') <p class="text-red-600 text-xs mt-1.5" role="alert">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit"
                            class="w-full bg-copper-500 hover:bg-copper-600 text-white py-3 rounded-lg font-medium transition">
                        Buat Pesanan
                    </button>
                    <x-trust-badges />
                </div>
            </div>
        </div>
    </form>

    <form id="voucher-form" method="POST" action="{{ route('customer.checkout.voucher') }}">@csrf</form>
    <form id="voucher-pick-form" method="POST" action="{{ route('customer.checkout.voucher') }}">@csrf</form>
    <form id="voucher-remove-form" method="POST" action="{{ route('customer.checkout.voucher.remove') }}">@csrf @method('DELETE')</form>
</div>
<script>
    // Pasang/lepas voucher memuat ulang halaman. Isian form (alamat, kota, kode pos, catatan, layanan kirim) dibawa serta
    // lewat field draft[...] supaya tidak hilang.
    document.addEventListener('DOMContentLoaded', function () {
        var main = document.querySelector('form[action="{{ route('customer.checkout.store') }}"]');
        if (!main) return;
        ['voucher-form', 'voucher-pick-form', 'voucher-remove-form'].forEach(function (id) {
            var f = document.getElementById(id);
            if (!f) return;
            f.addEventListener('submit', function () {
                f.querySelectorAll('input[data-draft]').forEach(function (n) { n.remove(); });
                ['name', 'phone', 'address', 'province_id', 'city_id', 'postal_code', 'note', 'shipping_method_id'].forEach(function (n) {
                    var el = main.elements[n];
                    if (!el) return;
                    var h = document.createElement('input');
                    h.type = 'hidden'; h.name = 'draft[' + n + ']'; h.value = el.value || ''; h.setAttribute('data-draft', '1');
                    f.appendChild(h);
                });
            });
        });
    });
</script>
<script>
    window.checkoutForm = function (cfg) {
        return {
            cityId: cfg.cityId || '',
            methodId: cfg.methodId || '',
            postal: cfg.postal || '',
            methods: [],
            loading: false,
            message: '',
            configured: cfg.configured,
            base: cfg.base,
            get groups() {
                const g = [
                    { key: 'own', label: 'Kargo Kami', hint: 'Armada pengiriman toko', items: this.methods.filter(m => m.type === 'own') },
                    { key: 'external', label: 'Ekspedisi Lain', hint: 'JNE, J&T, dan kurir lainnya', items: this.methods.filter(m => m.type !== 'own') },
                ];
                return g.filter(x => x.items.length);
            },
            get selected() { return this.methods.find(m => String(m.id) === String(this.methodId)) || null; },
            get total() { return this.base + (this.selected ? this.selected.cost : 0); },
            rp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); },
            onPostal(v) { this.postal = v; if (v.length === 5) this.loadOptions(); },
            onCity(id) { this.cityId = id || ''; this.loadOptions(); },
            async loadOptions() {
                if (!this.configured) return;
                if (!this.cityId) { this.methods = []; this.methodId = ''; this.message = ''; return; }
                this.loading = true; this.message = '';
                try {
                    const res = await fetch(cfg.shippingUrl + '?city_id=' + encodeURIComponent(this.cityId) + '&postal_code=' + encodeURIComponent(this.postal), { headers: { 'Accept': 'application/json' } });
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
