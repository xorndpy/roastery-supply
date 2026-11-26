@extends('layouts.shop')

@section('title', $address->exists ? 'Ubah Alamat' : 'Tambah Alamat')

@section('content')
<x-page-header plain :title="$address->exists ? 'Ubah alamat' : 'Tambah alamat'" :crumbs="[['Beranda', route('home')], ['Buku alamat', route('customer.addresses.index')], [$address->exists ? 'Ubah' : 'Tambah']]" />

@php $input = 'w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500'; @endphp
<div class="container-x py-6 sm:py-10 pb-28">
    <form method="POST" action="{{ $address->exists ? route('customer.addresses.update', $address->id) : route('customer.addresses.store') }}" class="mx-auto max-w-2xl space-y-4 rounded-xl border border-stone-200 bg-white p-4 sm:p-6">
        @csrf
        @if($address->exists) @method('PUT') @endif

        <div>
            <label class="mb-2 block text-sm font-medium" for="label">Nama alamat *</label>
            <input id="label" type="text" name="label" value="{{ old('label', $address->label) }}" maxlength="40" required placeholder="Contoh: Kedai Utama, Gudang, Cabang Bandung" class="{{ $input }}">
            @error('label')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-medium" for="recipient_name">Nama penerima *</label>
                <input id="recipient_name" type="text" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name) }}" maxlength="100" required class="{{ $input }}">
                @error('recipient_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium" for="phone">No. telepon *</label>
                <input id="phone" type="text" name="phone" value="{{ old('phone', $address->phone) }}" maxlength="20" required class="{{ $input }}">
                @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label class="mb-2 block text-sm font-medium" for="address">Alamat lengkap *</label>
            <textarea id="address" name="address" rows="3" maxlength="500" required class="{{ $input }}">{{ old('address', $address->address) }}</textarea>
            @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <x-region-select :province-id="old('province_id', $address->province_id)" :city-id="old('city_id', $address->city_id)" />

        <div class="md:w-1/2">
            <label class="mb-2 block text-sm font-medium" for="postal_code">Kode pos *</label>
            <input id="postal_code" type="text" name="postal_code" value="{{ old('postal_code', $address->postal_code) }}" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" required class="{{ $input }}">
            @error('postal_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        @if($address->exists && $address->is_default)
            <p class="text-sm text-stone-500">Ini alamat utama Anda.</p>
        @elseif($isFirst)
            <p class="text-sm text-stone-500">Alamat pertama otomatis menjadi alamat utama.</p>
        @else
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="h-4 w-4 rounded border-stone-300" @checked(old('is_default'))> Jadikan alamat utama</label>
        @endif

        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('customer.addresses.index') }}" class="btn btn-outline">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan alamat</button>
        </div>
    </form>
</div>
@endsection
