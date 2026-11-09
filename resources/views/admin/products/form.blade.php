@extends('layouts.admin')

@section('title', $mode === 'create' ? 'Tambah Produk' : 'Edit Produk: ' . $product->name)

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.products.index') }}" class="text-sm text-stone-500 hover:text-stone-700">
        ← Kembali ke Daftar Produk
    </a>
</div>

@if(session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
        <div class="font-medium mb-2">Ada kesalahan:</div>
        <ul class="list-disc list-inside text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST"
      action="{{ $mode === 'create' ? route('admin.products.store') : route('admin.products.update', $product->id) }}"
      enctype="multipart/form-data">
    @csrf
    @if($mode === 'edit')
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- LEFT: Main Info --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Basic Info --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Informasi Produk</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Nama Produk *</label>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500"
                               placeholder="Contoh: Espresso Machine Pro">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Kategori *</label>
                            <select name="category_id" required
                                    class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Pilih Kategori</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1.5">Merek *</label>
                            <select name="brand_id" required
                                    class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">Pilih Merek</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>
                                        {{ $brand->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('brand_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">SKU</label>
                        <input type="text" name="sku" value="{{ old('sku', $product->sku) }}"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500"
                               placeholder="Kosongkan untuk auto-generate (RS-XXX-001)">
                        <p class="text-xs text-stone-500 mt-1">Kosongkan untuk generate otomatis.</p>
                        @error('sku') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">Deskripsi *</label>
                        <textarea name="description" rows="6" required
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500"
                                  placeholder="Jelaskan spesifikasi, fitur, dan keunggulan produk...">{{ old('description', $product->description) }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Pricing & Stock --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Harga & Stok</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Harga (Rp) *</label>
                        <input type="text" inputmode="numeric" data-rupiah name="price" value="{{ old('price', $product->price) }}"
                               required min="0" step="1000"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">Berat (gram)</label>
                        <input type="number" name="weight" value="{{ old('weight', $product->weight) }}"
                               min="0"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">{{ $product->exists ? 'Stok Saat Ini' : 'Stok Awal *' }}</label>
                        @if($product->exists)
                            <input type="number" value="{{ $product->stock }}" readonly disabled
                                   class="w-full border border-stone-200 bg-stone-50 text-stone-500 rounded-lg px-3 py-2">
                            <p class="text-xs text-stone-500 mt-1">
                                Ubah stok lewat <a href="{{ route('admin.stock.index', ['search' => $product->sku]) }}" class="text-orange-600 underline">menu Stok</a>
                                agar tercatat di riwayat.
                            </p>
                        @else
                            <input type="number" name="stock" value="{{ old('stock', 0) }}"
                                   required min="0"
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                            @error('stock') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">Peringatan Stok Menipis *</label>
                        <input type="number" name="stock_threshold" value="{{ old('stock_threshold', $product->stock_threshold ?? 5) }}"
                               required min="0"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        <p class="text-xs text-stone-500 mt-1">Notifikasi muncul jika stok ≤ nilai ini.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Sidebar --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Foto produk: grid + drag & drop. Foto pertama = foto utama di katalog. --}}
            @php
                $existingImages = $mode === 'edit'
                    ? $product->images->map(fn ($i) => ['id' => $i->id, 'url' => thumb_url($i->image), 'full' => media_url($i->image), 'removed' => false])->values()
                    : collect();
            @endphp
            <div class="bg-white p-6 rounded-lg border border-stone-200"
                 x-data="productImages(@js($existingImages), 5, 2)">
                <div class="flex items-baseline justify-between mb-1">
                    <h2 class="font-bold !mb-0 !border-0 !pb-0">Foto Produk</h2>
                    <span class="text-xs text-stone-500 tabular-nums" x-text="total + '/' + max + ' foto'"></span>
                </div>
                <p class="text-xs text-stone-500 mb-4">Foto pertama menjadi foto utama di katalog. Pakai latar putih polos dan produk memenuhi frame; ruang putih berlebih dipotong otomatis.</p>

                <input type="hidden" name="images_touched" value="" x-init="$el.value = '1'">
                <template x-for="img in visibleExisting" :key="img.id">
                    <span><input type="hidden" name="keep_images[]" :value="img.id"><input type="hidden" name="image_order[]" :value="img.id"></span>
                </template>
                <input x-ref="input" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" tabindex="-1" aria-hidden="true" @change="pick($event)">

                <div class="grid grid-cols-2 gap-2.5 rounded-xl border border-dashed p-2.5 transition"
                     :class="dragging ? 'border-orange-500 bg-orange-50' : 'border-stone-300 bg-stone-50'"
                     @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)">

                    <template x-for="(img, i) in visibleExisting" :key="'e' + img.id">
                        <div class="group relative aspect-square overflow-hidden rounded-lg border border-stone-200 bg-white" :class="i === 0 ? 'ring-2 ring-orange-500' : ''">
                            <img :src="img.url" x-on:error="$el.src = img.full" :alt="'Foto produk ' + (i + 1)" class="h-full w-full object-contain p-1.5">
                            <span x-show="i === 0" class="absolute left-1.5 top-1.5 rounded bg-orange-500 px-1.5 py-0.5 text-[11px] font-medium text-white">Utama</span>
                            <div class="absolute inset-x-0 bottom-0 flex justify-end gap-1 bg-gradient-to-t from-black/50 to-transparent p-1.5 opacity-100 sm:opacity-0 sm:transition sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                                <button type="button" x-show="i !== 0" @click="makePrimary(img)" class="flex h-8 items-center gap-1 rounded-md bg-white/95 px-2 text-[11px] font-medium text-stone-800 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500" aria-label="Jadikan foto utama">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.8l2.5 5.1 5.6.8-4.1 4 1 5.6-5-2.7-5 2.7 1-5.6-4.1-4 5.6-.8L12 3.8z"/></svg>Utama</button>
                                <button type="button" @click="remove(img)" class="flex h-8 w-8 items-center justify-center rounded-md bg-white/95 text-red-700 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="Hapus foto">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12"/></svg></button>
                            </div>
                        </div>
                    </template>

                    <template x-for="(f, i) in added" :key="'n' + f.url">
                        <div class="group relative aspect-square overflow-hidden rounded-lg border border-stone-200 bg-white" :class="(visibleExisting.length === 0 && i === 0) ? 'ring-2 ring-orange-500' : ''">
                            <img :src="f.url" :alt="'Foto baru: ' + f.name" class="h-full w-full object-contain p-1.5">
                            <span x-show="visibleExisting.length === 0 && i === 0" class="absolute left-1.5 top-1.5 rounded bg-orange-500 px-1.5 py-0.5 text-[11px] font-medium text-white">Utama</span>
                            <span x-show="!(visibleExisting.length === 0 && i === 0)" class="absolute left-1.5 top-1.5 rounded bg-stone-800/80 px-1.5 py-0.5 text-[11px] text-white">Baru</span>
                            <div class="absolute inset-x-0 bottom-0 flex justify-end bg-gradient-to-t from-black/50 to-transparent p-1.5 opacity-100 sm:opacity-0 sm:transition sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                                <button type="button" @click="removeNew(i)" class="flex h-8 w-8 items-center justify-center rounded-md bg-white/95 text-red-700 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500" aria-label="Batalkan foto baru">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
                            </div>
                        </div>
                    </template>

                    <button type="button" x-show="total < max" @click="$refs.input.click()"
                            class="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-stone-300 bg-white text-stone-600 transition hover:border-orange-500 hover:text-orange-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                            :class="total === 0 ? 'col-span-2 !aspect-[2/1]' : ''">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V5M7.5 9.5L12 5l4.5 4.5M5 19h14"/></svg>
                        <span class="text-xs font-medium">Tambah foto</span>
                        <span class="text-[11px] text-stone-500">atau seret ke sini</span>
                    </button>
                </div>

                <p x-show="error" x-text="error" class="mt-2 text-xs text-red-600" role="alert"></p>
                <p x-show="removedCount > 0" class="mt-2 text-xs text-stone-600">
                    <span x-text="removedCount"></span> foto akan dihapus saat disimpan.
                    <button type="button" @click="restoreAll()" class="font-medium text-orange-700 underline">Batalkan</button>
                </p>
                <p class="mt-2 text-[11px] text-stone-500">JPG, PNG, atau WebP. Maks 2 MB per foto, maks 5 foto.</p>
                @error('images') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                @error('images.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Status --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Status</h2>

                <div class="space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 text-orange-500 rounded">
                        <div>
                            <div class="font-medium text-sm">Aktif</div>
                            <div class="text-xs text-stone-500">Tampil di katalog publik</div>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1"
                               {{ old('is_featured', $product->is_featured ?? false) ? 'checked' : '' }}
                               class="w-4 h-4 text-orange-500 rounded">
                        <div>
                            <div class="font-medium text-sm">Unggulan</div>
                            <div class="text-xs text-stone-500">Tampil di sorotan beranda katalog</div>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Submit --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200 sticky top-24">
                <button type="submit"
                        class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-lg font-medium transition">
                    {{ $mode === 'create' ? 'Simpan Produk' : 'Update Produk' }}
                </button>
                <a href="{{ route('admin.products.index') }}"
                   class="block text-center text-sm text-stone-500 hover:text-stone-700 mt-3">
                    Batal
                </a>
            </div>
        </div>
    </div>
</form>

@endsection