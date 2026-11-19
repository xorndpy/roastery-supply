@extends('layouts.admin')

@php
    $isEdit = $sale !== null;
    $rows = old('items', $isEdit ? $sale->items->map(fn ($i) => ['product_id' => $i->product_id, 'sale_price' => (int) $i->sale_price, 'quota' => $i->quota, 'max_per_order' => $i->max_per_order])->values()->all() : [['product_id' => '', 'sale_price' => '', 'quota' => '', 'max_per_order' => '']]);
    $fmt = fn ($d) => $d ? $d->copy()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') : '';
@endphp
@section('title', $isEdit ? 'Ubah Flash Sale' : 'Kampanye Flash Sale')

@section('content')
<div class="mb-6"><a href="{{ route('admin.flash-sales.index') }}" class="text-sm text-stone-500 hover:text-stone-700">Kembali ke Flash Sale</a></div>

@if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $isEdit ? route('admin.flash-sales.update', $sale->id) : route('admin.flash-sales.store') }}" class="max-w-4xl space-y-6 rounded-lg border border-stone-200 bg-white p-6"
      x-data='flashSaleForm(@json($products), @json($rows), @json($sold))'>
    @csrf @if($isEdit) @method('PUT') @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="md:col-span-3"><label class="mb-1.5 block text-sm font-medium">Nama kampanye *</label><input type="text" name="name" required maxlength="80" value="{{ old('name', $sale->name ?? '') }}" placeholder="Mis. Promo Akhir Pekan" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label class="mb-1.5 block text-sm font-medium">Mulai (WIB) *</label><input type="datetime-local" name="starts_at" required value="{{ old('starts_at', $fmt($sale->starts_at ?? null)) }}" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label class="mb-1.5 block text-sm font-medium">Selesai (WIB) *</label><input type="datetime-local" name="ends_at" required value="{{ old('ends_at', $fmt($sale->ends_at ?? null)) }}" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <label class="flex items-end gap-2 pb-2 text-sm text-stone-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="rounded border-stone-300" @checked(old('is_active', $sale->is_active ?? true))> Aktif</label>
    </div>

    <div>
        <h3 class="mb-3 font-semibold">Produk promo</h3>
        <div class="space-y-3">
            <template x-for="(r, i) in rows" :key="i">
                <div class="grid items-end gap-3 rounded-lg border border-stone-200 p-3 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label class="mb-1 block text-xs text-stone-500">Produk</label>
                        <select :name="`items[${i}][product_id]`" x-model="r.product_id" required class="w-full rounded-lg border border-stone-300 px-2 py-2 text-sm">
                            <option value="">Pilih produk</option>
                            <template x-for="p in products" :key="p.id"><option :value="p.id" x-text="p.name + ' - Rp ' + Number(p.price).toLocaleString('id-ID')"></option></template>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="mb-1 block text-xs text-stone-500">Harga promo (Rp) <span class="text-copper-600" x-show="pct(r) !== null" x-text="'(-' + pct(r) + '%)'"></span></label>
                        <input type="text" inputmode="numeric" data-rupiah autocomplete="off" :name="`items[${i}][sale_price]`" x-model="r.sale_price" @input="r.sale_price = fmt($event.target.value)" required placeholder="mis. 285.000" class="w-full rounded-lg border border-stone-300 px-2 py-2 text-sm">
                    </div>
                    <div class="md:col-span-2"><label class="mb-1 block text-xs text-stone-500">Kuota</label><input type="number" min="1" :name="`items[${i}][quota]`" x-model="r.quota" required class="w-full rounded-lg border border-stone-300 px-2 py-2 text-sm"></div>
                    <div class="md:col-span-2"><label class="mb-1 block text-xs text-stone-500">Maks/pesanan</label><input type="number" min="1" :name="`items[${i}][max_per_order]`" x-model="r.max_per_order" placeholder="bebas" class="w-full rounded-lg border border-stone-300 px-2 py-2 text-sm"></div>
                    <div class="md:col-span-1 text-right"><button type="button" @click="rows.splice(i, 1)" x-show="rows.length > 1" class="text-xs text-red-600 underline">Hapus</button></div>
                </div>
            </template>
        </div>
        <button type="button" @click="add()" class="mt-3 text-sm text-copper-700 underline">+ Tambah produk</button>
        <p class="mt-2 text-xs text-stone-500">Harga promo harus di bawah harga normal. Kuota yang sudah terjual tidak bisa dikurangi. Pesanan yang batal mengembalikan kuota otomatis.</p>
    </div>

    <div class="flex gap-3">
        <button class="rounded-lg bg-copper-500 px-6 py-2.5 font-medium text-white hover:bg-copper-600">{{ $isEdit ? 'Simpan perubahan' : 'Buat kampanye' }}</button>
        <a href="{{ route('admin.flash-sales.index') }}" class="rounded-lg border border-stone-300 px-6 py-2.5 text-stone-700">Batal</a>
    </div>
</form>
@endsection
