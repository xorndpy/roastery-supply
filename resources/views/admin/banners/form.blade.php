@extends('layouts.admin')

@php $isEdit = $banner->exists; @endphp
@section('title', $isEdit ? 'Edit Banner' : 'Tambah Banner')

@section('content')

<div class="mb-6"><a href="{{ route('admin.banners.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Banner</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" enctype="multipart/form-data"
      action="{{ $isEdit ? route('admin.banners.update', $banner->id) : route('admin.banners.store') }}"
      class="max-w-2xl bg-white p-6 rounded-lg border border-stone-200 space-y-4">
    @csrf @if($isEdit) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1.5">Judul *</label>
        <input type="text" name="title" value="{{ old('title', $banner->title) }}" required maxlength="150" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>

    <div x-data="{ src: null, existing: @js($isEdit ? media_url($banner->image) : null) }">
        <label class="block text-sm font-medium mb-1.5" for="banner-image">Gambar {{ $isEdit ? '' : '*' }}</label>
        <div class="relative overflow-hidden rounded-xl border bg-stone-50" :class="(src || existing) ? 'border-stone-200' : 'border-dashed border-stone-300'" style="aspect-ratio: 8 / 3">
            <img x-show="src || existing" x-cloak :src="src || existing" alt="Pratinjau banner" class="absolute inset-0 h-full w-full object-cover">
            <div x-show="src || existing" x-cloak class="pointer-events-none absolute inset-y-0 left-[20%] w-[60%] border-x border-dashed border-white/90"></div>
            <div x-show="!(src || existing)" class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-stone-500">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5"/></svg>
                <span class="text-sm">Pilih foto banner</span>
            </div>
        </div>
        <div class="mt-2 flex items-center gap-3">
            <label class="inline-flex cursor-pointer items-center rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 hover:bg-stone-50 focus-within:ring-2 focus-within:ring-orange-500">
                <input id="banner-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" {{ $isEdit ? '' : 'required' }} class="sr-only"
                       @change="const f = $event.target.files[0]; src = f ? URL.createObjectURL(f) : null">
                <span x-text="(src || existing) ? 'Ganti foto' : 'Pilih foto'"></span>
            </label>
            <span class="text-xs text-stone-500">JPG/PNG/WEBP, maks 4 MB</span>
        </div>
        <p class="text-xs text-stone-500 mt-2 leading-relaxed">Rasio <strong>8:3</strong>, misalnya <strong>2400 × 900 px</strong>. Pratinjau ini sama dengan tampilan di komputer. Garis putus-putus menandai bagian tengah yang <strong>selalu terlihat di HP</strong>, jadi taruh teks dan objek penting di antaranya.</p>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1.5">Tampil di</label>
        <select name="placement" class="w-full border border-stone-300 rounded-lg px-3 py-2">
            @foreach(\App\Models\Banner::PLACEMENTS as $val => $label)
                <option value="{{ $val }}" @selected(old('placement', $banner->placement ?? 'beranda') === $val)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="text-xs text-stone-500 mt-1">Beranda: banner lebar di bawah hero (maks. 2). Katalog: slider di paling atas halaman Produk; pasang 3–5 foto agar berputar (maks. 5 yang tampil, urutan sesuai daftar Banner).</p>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1.5">Link tujuan</label>
        <input type="text" name="link" value="{{ old('link', $banner->link) }}" placeholder="/katalog atau https://..." class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Mulai tayang</label>
            <input type="datetime-local" name="start_at" value="{{ old('start_at', $banner->start_at?->format('Y-m-d\TH:i')) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Selesai tayang</label>
            <input type="datetime-local" name="end_at" value="{{ old('end_at', $banner->end_at?->format('Y-m-d\TH:i')) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>
    <p class="text-xs text-stone-500 -mt-2">Kosongkan jadwal agar banner selalu tayang.</p>

    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active))> Aktif</label>

    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">{{ $isEdit ? 'Simpan' : 'Tambah Banner' }}</button>
</form>
@endsection
