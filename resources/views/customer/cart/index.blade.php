@extends('layouts.shop')

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
                                {{-- Gaya marketplace: ubah jumlah langsung tersimpan (tanpa tombol "Perbarui"). --}}
                                <div x-data="cartLine({ url: @js(route('customer.cart.update', $id)), id: @js((string) $id), qty: {{ (int) $item['quantity'] }}, max: {{ max(1, (int) $item['stock']) }} })" class="flex flex-col">
                                    <div class="flex items-center gap-3">
                                        <div class="inline-flex h-9 items-center rounded-lg border border-stone-300 bg-white" role="group" aria-label="Jumlah {{ $item['name'] }}">
                                            <button type="button" @click="step(-1)" :disabled="qty <= 1" class="h-full w-9 text-lg text-stone-700 transition hover:text-orange-600 disabled:cursor-not-allowed disabled:text-stone-300" aria-label="Kurangi jumlah">−</button>
                                            <input type="text" inputmode="numeric" autocomplete="off" x-model="qty" @input="digits()" @change="commit()" @keydown.enter.prevent="commit()"
                                                   class="h-full w-12 border-0 border-x border-stone-300 bg-transparent p-0 text-center text-sm font-medium focus:ring-1 focus:ring-orange-500" aria-label="Jumlah">
                                            <button type="button" @click="step(1)" :disabled="qty >= max" class="h-full w-9 text-lg text-stone-700 transition hover:text-orange-600 disabled:cursor-not-allowed disabled:text-stone-300" aria-label="Tambah jumlah">+</button>
                                        </div>
                                        <span class="text-xs text-stone-500">Stok {{ (int) $item['stock'] }}</span>
                                        <span x-show="busy" x-cloak class="text-xs text-stone-400">Menyimpan…</span>
                                    </div>
                                    <p x-show="msg" x-cloak x-text="msg" role="status" class="mt-1.5 text-[13px] text-red-600"></p>
                                </div>
                                <form method="POST" action="{{ route('customer.cart.destroy', $id) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[13px] text-stone-400 hover:text-red-600">Hapus</button>
                                </form>
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-xs text-stone-500">Subtotal</div>
                            <div class="font-semibold" id="line-{{ $id }}">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="rounded-xl border border-stone-200 bg-white p-6 lg:sticky lg:top-24">
                <h2 class="heading mb-4 text-lg">Ringkasan Pesanan</h2>
                <div class="flex justify-between py-1.5 text-sm text-stone-500"><span>Subtotal</span><span data-cart-total>Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                <div class="flex justify-between py-1.5 text-sm text-stone-500"><span>Ongkos kirim</span><span>Dihitung saat checkout</span></div>
                <div class="mt-3 flex justify-between border-t border-stone-200 pt-4 text-[17px] font-semibold">
                    <span>Total</span><span class="text-orange-500" data-cart-total>Rp {{ number_format($total, 0, ',', '.') }}</span>
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

@push('scripts')
<script>
function cartLine(cfg) {
    return {
        qty: cfg.qty, max: cfg.max, busy: false, msg: '', timer: null, seq: 0,
        digits() { this.qty = String(this.qty).replace(/\D/g, ''); },
        step(d) { this.set(Number(this.qty) + d); },
        commit() { this.set(this.qty); },
        set(v) {
            v = parseInt(v, 10);
            if (isNaN(v) || v < 1) v = 1;
            if (v > this.max) { v = this.max; this.msg = 'Stok tersedia hanya ' + this.max + '.'; } else { this.msg = ''; }
            this.qty = v;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), 350);       // simpan otomatis setelah berhenti mengetik
        },
        async save() {
            const mine = ++this.seq; this.busy = true;
            try {
                const res = await fetch(cfg.url, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ quantity: this.qty }) });
                const j = await res.json();
                if (mine !== this.seq) return;                     // sudah ada perubahan yang lebih baru
                if (!res.ok) { this.msg = j.message || 'Gagal memperbarui jumlah.'; return; }
                this.qty = j.quantity; this.max = j.max;
                if (j.message) this.msg = j.message;
                document.getElementById('line-' + cfg.id).textContent = j.line_subtotal_label;
                document.querySelectorAll('[data-cart-total]').forEach(e => e.textContent = j.total_label);
            } catch (e) { this.msg = 'Koneksi bermasalah. Coba lagi.'; }
            finally { if (mine === this.seq) this.busy = false; }
        },
    };
}
</script>
@endpush
