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
                        <input type="number" name="price" value="{{ old('price', $product->price) }}"
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
                        <label class="block text-sm font-medium mb-1.5">Stok *</label>
                        <input type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}"
                               required min="0"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                        @error('stock') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
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

            {{-- 3D Model --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Model 3D (Opsional)</h2>

                @if($product->model_3d)
                    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm">
                        <div class="text-blue-800 font-medium">Model 3D sudah diunggah</div>
                        <div class="text-blue-600 text-xs mt-1">{{ basename($product->model_3d) }}</div>
                    </div>
                @endif

                <input type="file" name="model_3d" accept=".glb,.gltf"
                       class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-stone-500 mt-1">Format: .glb atau .gltf. Max 10MB.</p>
                @error('model_3d') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- RIGHT: Sidebar --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Images --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Gambar Produk</h2>

                {{-- Existing images (edit mode) --}}
                @if($mode === 'edit' && $product->images->count())
                    <div class="mb-4">
                        <div class="text-xs text-stone-500 mb-2">Gambar saat ini (centang untuk hapus):</div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($product->images as $image)
                                <label class="relative group cursor-pointer">
                                    <input type="checkbox" name="keep_images[]" value="{{ $image->id }}"
                                           class="absolute top-1 left-1 z-10 w-4 h-4" checked>
                                    <img src="{{ asset('storage/' . $image->image) }}"
                                         alt="Image"
                                         class="w-full h-24 object-cover rounded border border-stone-200">
                                    @if($image->is_primary)
                                        <span class="absolute bottom-1 right-1 bg-orange-500 text-white text-xs px-1.5 py-0.5 rounded">
                                            Utama
                                        </span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-stone-500 mt-2">Uncheck untuk hapus gambar.</p>
                    </div>
                @endif

                {{-- New images --}}
                <div>
                    <label class="block text-sm font-medium mb-1.5">
                        {{ $mode === 'create' ? 'Upload Gambar (max 5)' : 'Tambah Gambar Baru' }}
                    </label>
                    <input type="file" name="images[]" accept="image/*" multiple
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-stone-500 mt-1">Format: JPG/PNG/WebP. Max 2MB/gambar.</p>
                    @error('images') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    @error('images.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
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
                            <div class="font-medium text-sm">Featured</div>
                            <div class="text-xs text-stone-500">Tampil di home page</div>
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