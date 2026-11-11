@php
    $specRows = old('specs', collect($product->specs ?? [])->map(fn ($r) => ['key' => $r['key'] ?? '', 'value' => $r['value'] ?? ''])->all());
    $variantRows = old('variants', $product->exists
        ? $product->variants->map(fn ($v) => ['type' => $v->type, 'name' => $v->name, 'color_hex' => $v->color_hex, 'price_adjust' => (float) $v->price_adjust])->all()
        : []);
@endphp

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2"
     x-data="{ specs: @js(array_values($specRows)), variants: @js(array_values($variantRows)) }">

    <div class="rounded-lg border border-stone-200 bg-white p-6">
        <h2 class="mb-1 font-bold">Spesifikasi</h2>
        <p class="mb-4 text-xs text-stone-500">Tampil sebagai tabel di tab Spesifikasi. Contoh: Boiler → 5 liter.</p>
        <template x-for="(row, i) in specs" :key="i">
            <div class="mb-2 flex gap-2">
                <input type="text" :name="`specs[${i}][key]`" x-model="row.key" maxlength="40" placeholder="Nama (mis. Daya)" class="w-2/5 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                <input type="text" :name="`specs[${i}][value]`" x-model="row.value" maxlength="120" placeholder="Nilai (mis. 1800 watt)" class="flex-1 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                <button type="button" @click="specs.splice(i, 1)" class="px-2 text-stone-400 hover:text-red-600" aria-label="Hapus">✕</button>
            </div>
        </template>
        <button type="button" @click="specs.length < 30 && specs.push({ key: '', value: '' })" class="text-sm font-medium text-orange-600">+ Tambah spesifikasi</button>
    </div>

    <div class="rounded-lg border border-stone-200 bg-white p-6">
        <h2 class="mb-1 font-bold">Varian</h2>
        <p class="mb-4 text-xs text-stone-500">Satu jenis varian per produk (mis. Warna). Selisih harga boleh minus. Stok tetap per produk.</p>
        <template x-for="(row, i) in variants" :key="i">
            <div class="mb-2 grid grid-cols-12 gap-2">
                <input type="text" :name="`variants[${i}][type]`" x-model="row.type" maxlength="30" placeholder="Jenis" class="col-span-3 rounded-lg border border-stone-300 px-2 py-2 text-sm">
                <input type="text" :name="`variants[${i}][name]`" x-model="row.name" maxlength="60" placeholder="Nama (White)" class="col-span-3 rounded-lg border border-stone-300 px-2 py-2 text-sm">
                <input type="color" :name="`variants[${i}][color_hex]`" :value="row.color_hex || '#cccccc'" @input="row.color_hex = $event.target.value" class="col-span-2 h-10 w-full rounded-lg border border-stone-300">
                <input type="number" step="1000" :name="`variants[${i}][price_adjust]`" x-model="row.price_adjust" placeholder="+Rp" class="col-span-3 rounded-lg border border-stone-300 px-2 py-2 text-sm">
                <button type="button" @click="variants.splice(i, 1)" class="col-span-1 text-stone-400 hover:text-red-600" aria-label="Hapus">✕</button>
            </div>
        </template>
        <button type="button" @click="variants.length < 12 && variants.push({ type: variants[0]?.type || 'Warna', name: '', color_hex: '#cccccc', price_adjust: 0 })" class="text-sm font-medium text-orange-600">+ Tambah varian</button>
    </div>
</div>
