@extends('layouts.admin')

@section('title', $supplier->exists ? 'Ubah Supplier' : 'Tambah Supplier')

@section('content')
@php $in = 'w-full rounded-lg border border-stone-300 px-3 py-2 text-sm'; @endphp
<div class="mb-6"><a href="{{ route('admin.suppliers.index') }}" class="text-sm text-stone-500 hover:text-stone-700">Kembali ke Supplier</a>
    <h1 class="mt-2 text-2xl font-bold text-stone-900">{{ $supplier->exists ? 'Ubah supplier' : 'Tambah supplier' }}</h1></div>

@if($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $supplier->exists ? route('admin.suppliers.update', $supplier->id) : route('admin.suppliers.store') }}" class="max-w-2xl space-y-4 rounded-lg border border-stone-200 bg-white p-6">
    @csrf @if($supplier->exists) @method('PUT') @endif
    <div><label class="mb-1 block text-sm font-medium">Nama supplier *</label><input type="text" name="name" value="{{ old('name', $supplier->name) }}" required maxlength="120" class="{{ $in }}"></div>
    <div class="grid gap-4 md:grid-cols-2">
        <div><label class="mb-1 block text-sm font-medium">Nama kontak</label><input type="text" name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}" maxlength="100" class="{{ $in }}"></div>
        <div><label class="mb-1 block text-sm font-medium">Telepon</label><input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="30" class="{{ $in }}"></div>
    </div>
    <div><label class="mb-1 block text-sm font-medium">Email</label><input type="email" name="email" value="{{ old('email', $supplier->email) }}" maxlength="150" class="{{ $in }}"></div>
    <div><label class="mb-1 block text-sm font-medium">Alamat</label><textarea name="address" rows="2" maxlength="500" class="{{ $in }}">{{ old('address', $supplier->address) }}</textarea></div>
    <div><label class="mb-1 block text-sm font-medium">Catatan</label><textarea name="note" rows="2" maxlength="500" class="{{ $in }}">{{ old('note', $supplier->note) }}</textarea></div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-stone-300" @checked(old('is_active', $supplier->is_active))> Supplier aktif</label>
    <div class="flex justify-end gap-2 pt-2"><a href="{{ route('admin.suppliers.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm">Batal</a><button class="rounded-lg bg-stone-900 px-5 py-2 text-sm font-medium text-white">Simpan</button></div>
</form>
@endsection
