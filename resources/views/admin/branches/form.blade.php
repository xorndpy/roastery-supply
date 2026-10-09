@extends('layouts.admin')

@php $isEdit = $branch->exists; @endphp
@section('title', $isEdit ? 'Edit Cabang' : 'Tambah Cabang')

@section('content')

<div class="mb-6"><a href="{{ route('admin.branches.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Cabang</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.branches.update', $branch->id) : route('admin.branches.store') }}" class="max-w-2xl bg-white p-6 rounded-lg border border-stone-200 space-y-4">
    @csrf @if($isEdit) @method('PUT') @endif
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Nama cabang *</label>
            <input type="text" name="name" value="{{ old('name', $branch->name) }}" required maxlength="100" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Telepon</label>
            <input type="text" name="phone" value="{{ old('phone', $branch->phone) }}" maxlength="30" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Alamat *</label>
        <textarea name="address" rows="3" required maxlength="500" class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ old('address', $branch->address) }}</textarea>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Latitude</label>
            <input type="text" inputmode="decimal" name="latitude" value="{{ old('latitude', $branch->latitude) }}" placeholder="-6.2088" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Longitude</label>
            <input type="text" inputmode="decimal" name="longitude" value="{{ old('longitude', $branch->longitude) }}" placeholder="106.8456" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>
    <p class="text-xs text-stone-500 -mt-2">Cara dapat koordinat: buka Google Maps → klik kanan di lokasi → klik angka koordinat untuk menyalin.</p>
    <div>
        <label class="block text-sm font-medium mb-1.5">Link Google Maps (opsional)</label>
        <input type="url" name="maps_url" value="{{ old('maps_url', $branch->maps_url) }}" placeholder="https://maps.app.goo.gl/..." class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active))> Tampilkan di website</label>
    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">{{ $isEdit ? 'Simpan' : 'Tambah' }}</button>
</form>
@endsection
