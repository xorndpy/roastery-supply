@extends('layouts.admin')

@section('title', $po->exists ? 'Ubah Pembelian' : 'Buat Pembelian')

@section('content')
@php $in = 'w-full rounded-lg border border-stone-300 px-3 py-2 text-sm'; @endphp
<div class="mb-6"><a href="{{ $po->exists ? route('admin.purchases.show', $po->id) : route('admin.purchases.index') }}" class="text-sm text-stone-500 hover:text-stone-700">Kembali</a>
    <h1 class="mt-2 text-2xl font-bold text-stone-900">{{ $po->exists ? 'Ubah ' . $po->po_number : 'Buat pembelian' }}</h1>
    <p class="mt-1 text-sm text-stone-500">Disimpan sebagai draft. Setelah dipesan ke supplier, tandai Dipesan di halaman detail.</p></div>

@if($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $po->exists ? route('admin.purchases.update', $po->id) : route('admin.purchases.store') }}"
      x-data="poForm(@js($products), @js($rows), {{ (float) old('shipping_cost', $po->shipping_cost) }})" class="space-y-5">
    @csrf @if($po->exists) @method('PUT') @endif

    <div class="grid gap-4 rounded-lg border border-stone-200 bg-white p-5 md:grid-cols-3">
        <div>
            <label class="mb-1 block text-sm font-medium">Supplier *</label>
            <select name="supplier_id" required class="{{ $in }}">
                <option value="">Pilih supplier</option>
                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', $po->supplier_id) === $s->id)>{{ $s->name }}</option>@endforeach
            </select>
            <a href="{{ route('admin.suppliers.create') }}" class="mt-1 inline-block text-xs text-stone-500 underline underline-offset-2">+ Tambah supplier baru</a>
        </div>
        <div><label class="mb-1 block text-sm font-medium">Estimasi tiba</label><input type="date" name="expected_at" value="{{ old('expected_at', $po->expected_at?->format('Y-m-d')) }}" class="{{ $in }}"></div>
        <div><label class="mb-1 block text-sm font-medium">Ongkir / bea masuk (Rp)</label><input type="number" name="shipping_cost" min="0" step="1" x-model.number="shipping" class="{{ $in }}"></div>
    </div>

    <div class="rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500"><tr><th class="px-4 py-3 font-medium">Produk</th><th class="w-28 px-4 py-3 font-medium">Jumlah</th><th class="w-44 px-4 py-3 font-medium">Harga beli / unit (Rp)</th><th class="w-40 px-4 py-3 text-right font-medium">Subtotal</th><th class="w-12"></th></tr></thead>
                <tbody>
                <template x-for="(r, i) in rows" :key="r.key">
                    <tr class="border-t border-stone-100 align-top">
                        <td class="px-4 py-2">
                            <select :name="`items[${i}][product_id]`" x-model.number="r.product_id" @change="fillCost(r)" required class="{{ $in }}">
                                <option value="">Pilih produk</option>
                                <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name + ' (' + (p.sku || '-') + ')'" :selected="p.id === r.product_id"></option></template>
                            </select>
                            <p class="mt-1 text-xs text-stone-500" x-show="hint(r)" x-text="hint(r)"></p>
                        </td>
                        <td class="px-4 py-2"><input type="number" :name="`items[${i}][qty]`" x-model.number="r.qty" min="1" step="1" required class="{{ $in }}"></td>
                        <td class="px-4 py-2"><input type="number" :name="`items[${i}][unit_cost]`" x-model.number="r.unit_cost" min="0" step="1" required class="{{ $in }}"></td>
                        <td class="px-4 py-2 text-right font-medium whitespace-nowrap" x-text="rp((r.qty || 0) * (r.unit_cost || 0))"></td>
                        <td class="px-2 py-2 text-center"><button type="button" @click="remove(i)" class="text-stone-400 hover:text-red-700" aria-label="Hapus baris">&times;</button></td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 p-4">
            <button type="button" @click="add()" class="row-btn">+ Tambah produk</button>
            <div class="text-sm text-right">
                <div class="text-stone-500">Subtotal <span class="ml-2 text-stone-900" x-text="rp(subtotal)"></span></div>
                <div class="text-stone-500">Ongkir / bea <span class="ml-2 text-stone-900" x-text="rp(shipping || 0)"></span></div>
                <div class="mt-1 font-bold">Total <span class="ml-2" x-text="rp(subtotal + (shipping || 0))"></span></div>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-stone-200 bg-white p-5"><label class="mb-1 block text-sm font-medium">Catatan</label><textarea name="note" rows="2" maxlength="500" class="{{ $in }}">{{ old('note', $po->note) }}</textarea></div>

    <div class="flex justify-end gap-2"><a href="{{ $po->exists ? route('admin.purchases.show', $po->id) : route('admin.purchases.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm">Batal</a><button class="rounded-lg bg-stone-900 px-5 py-2 text-sm font-medium text-white">Simpan draft</button></div>
</form>

<script>
    window.poForm = function (products, initial, shipping) {
        let k = 0;
        const blank = () => ({ key: ++k, product_id: '', qty: 1, unit_cost: '' });
        return {
            products, shipping: shipping || 0,
            rows: (initial && initial.length ? initial : [null]).map(r => r ? { key: ++k, product_id: Number(r.product_id), qty: Number(r.qty), unit_cost: Number(r.unit_cost) } : blank()),
            get subtotal() { return this.rows.reduce((s, r) => s + (Number(r.qty) || 0) * (Number(r.unit_cost) || 0), 0); },
            add() { this.rows.push(blank()); },
            remove(i) { this.rows.splice(i, 1); if (!this.rows.length) this.rows.push(blank()); },
            rp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); },
            find(r) { return this.products.find(p => p.id === Number(r.product_id)); },
            fillCost(r) { const p = this.find(r); if (p && !r.unit_cost && p.cost) r.unit_cost = p.cost; },
            hint(r) { const p = this.find(r); return p && p.cost ? 'HPP sekarang ' + this.rp(p.cost) : (p ? 'HPP belum diisi' : ''); },
        };
    };
</script>
@endsection
