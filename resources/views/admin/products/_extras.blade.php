@php
    $specRows = old('specs', collect($product->specs ?? [])->map(fn ($r) => ['key' => $r['key'] ?? '', 'value' => $r['value'] ?? ''])->all());
    $variantRows = old('variants', $product->exists
        ? $product->variants->map(fn ($v) => ['type' => $v->type, 'name' => $v->name, 'product_image_id' => $v->product_image_id])->all()
        : []);
    $photos = $product->exists ? $product->images->values()->map(fn ($i, $n) => ['id' => $i->id, 'label' => 'Foto ' . ($n + 1), 'url' => media_url($i->image)])->all() : [];
@endphp

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2"
     x-data="{ specs: @js(array_values($specRows)), variants: @js(array_values($variantRows)), photos: @js($photos),
               url(id) { const p = this.photos.find(p => String(p.id) === String(id)); return p ? p.url : null } }">

    <div class="rounded-lg border border-stone-200 bg-white p-6">
        <h2 class="mb-1 font-bold">Spesifikasi</h2>
        <p class="mb-4 text-xs text-stone-500">Tampil di kartu deskripsi, di bawah teks deskripsi. Contoh: Boiler → 5 liter.</p>
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
        <p class="mb-4 text-xs text-stone-500">
            Satu jenis varian per produk (mis. Warna). Pilih foto untuk tiap varian: foto itu tampil saat pembeli memilih varian.
            @if(!$product->exists || empty($photos)) <b>Simpan produk dengan foto dulu, lalu edit untuk memilih foto varian.</b>@endif
        </p>
        <template x-for="(row, i) in variants" :key="i">
            <div class="mb-3 flex items-center gap-2">
                <input type="text" :name="`variants[${i}][type]`" x-model="row.type" maxlength="30" placeholder="Jenis (Warna)" class="w-1/4 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                <input type="text" :name="`variants[${i}][name]`" x-model="row.name" maxlength="60" placeholder="Nama (Merah)" class="w-1/4 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                <select :name="`variants[${i}][product_image_id]`" x-model="row.product_image_id" class="min-w-0 flex-1 rounded-lg border border-stone-300 px-2 py-2 text-sm">
                    <option value="">Tanpa foto khusus</option>
                    <template x-for="p in photos" :key="p.id"><option :value="p.id" x-text="p.label" :selected="String(p.id) === String(row.product_image_id)"></option></template>
                </select>
                <img x-show="url(row.product_image_id)" :src="url(row.product_image_id)" alt="" class="h-10 w-10 flex-none rounded border border-stone-200 object-cover">
                <button type="button" @click="variants.splice(i, 1)" class="px-1 text-stone-400 hover:text-red-600" aria-label="Hapus">✕</button>
            </div>
        </template>
        <button type="button" @click="variants.length < 12 && variants.push({ type: variants[0]?.type || 'Warna', name: '', product_image_id: '' })" class="text-sm font-medium text-orange-600">+ Tambah varian</button>
    </div>
</div>
