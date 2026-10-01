@extends('layouts.admin')

@section('title', $mode === 'create' ? 'Tambah Kategori' : 'Edit Kategori: ' . $category->name)

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.categories.index') }}" class="text-sm text-stone-500 hover:text-stone-700">
        ← Kembali ke Daftar Kategori
    </a>
</div>

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
      action="{{ $mode === 'create' ? route('admin.categories.store') : route('admin.categories.update', $category->id) }}"
      enctype="multipart/form-data">
    @csrf
    @if($mode === 'edit')
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Main Info --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Informasi Kategori</h2>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Nama Kategori *</label>
                        <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500"
                               placeholder="Contoh: Espresso Machine">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">Parent Kategori (Opsional)</label>
                        <select name="parent_id"
                                class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">— Kategori Utama (tanpa parent) —</option>
                            @foreach($parents as $parent)
                                <option value="{{ $parent->id }}" {{ old('parent_id', $category->parent_id) == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-stone-500 mt-1">Kosongkan untuk jadikan kategori utama.</p>
                        @error('parent_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1.5">Deskripsi</label>
                        <textarea name="description" rows="4"
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500"
                                  placeholder="Deskripsi kategori (opsional)">{{ old('description', $category->description) }}</textarea>
                        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Sidebar --}}
        <div class="lg:col-span-1 space-y-6">

            {{-- Image --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Gambar Kategori</h2>

                @if($mode === 'edit' && $category->image)
                    <div class="mb-3">
                        <img src="{{ asset('storage/' . $category->image) }}"
                             alt="{{ $category->name }}"
                             class="w-full rounded border border-stone-200">
                    </div>
                @endif

                <input type="file" name="image" accept="image/*"
                       class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-stone-500 mt-1">JPG/PNG/WebP, max 2MB.</p>
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Status --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-4">Status</h2>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}
                           class="w-4 h-4 text-orange-500 rounded">
                    <div>
                        <div class="font-medium text-sm">Aktif</div>
                        <div class="text-xs text-stone-500">Tampil di katalog publik</div>
                    </div>
                </label>
            </div>

            {{-- Submit --}}
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <button type="submit"
                        class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-lg font-medium transition">
                    {{ $mode === 'create' ? 'Simpan Kategori' : 'Update Kategori' }}
                </button>
                <a href="{{ route('admin.categories.index') }}"
                   class="block text-center text-sm text-stone-500 hover:text-stone-700 mt-3">
                    Batal
                </a>
            </div>
        </div>
    </div>
</form>

@endsection