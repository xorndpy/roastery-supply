@extends('layouts.admin')

@php $isEdit = $partner->exists; @endphp
@section('title', $isEdit ? 'Edit Partner' : 'Tambah Partner')

@section('content')

<div class="mb-6"><a href="{{ route('admin.partners.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Partner</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" enctype="multipart/form-data" action="{{ $isEdit ? route('admin.partners.update', $partner->id) : route('admin.partners.store') }}" class="max-w-xl bg-white p-6 rounded-lg border border-stone-200 space-y-4">
    @csrf @if($isEdit) @method('PUT') @endif
    <div>
        <label class="block text-sm font-medium mb-1.5">Nama *</label>
        <input type="text" name="name" value="{{ old('name', $partner->name) }}" required maxlength="100" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Logo {{ $isEdit ? '' : '*' }}</label>
        @if($isEdit)<img src="{{ asset('storage/' . $partner->logo) }}" alt="" class="h-12 mb-2">@endif
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" {{ $isEdit ? '' : 'required' }} class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1.5">Link website</label>
            <input type="url" name="link" value="{{ old('link', $partner->link) }}" placeholder="https://" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Urutan</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $partner->sort_order ?? 0) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $partner->is_active))> Aktif</label>
    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">{{ $isEdit ? 'Simpan' : 'Tambah' }}</button>
</form>
@endsection
