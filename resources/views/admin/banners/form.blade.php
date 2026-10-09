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

    <div>
        <label class="block text-sm font-medium mb-1.5">Gambar {{ $isEdit ? '' : '*' }}</label>
        @if($isEdit)<img src="{{ asset('storage/' . $banner->image) }}" alt="" class="h-24 mb-2 rounded border border-stone-200">@endif
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" {{ $isEdit ? '' : 'required' }} class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
        <p class="text-xs text-stone-500 mt-1">JPG/PNG/WEBP, maks 4 MB. Disarankan rasio lebar (mis. 1920×800).</p>
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
