@extends('layouts.shop')

@section('title', 'Profil Saya')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <div class="mb-6">
        <a href="{{ route('customer.dashboard') }}" class="text-sm text-stone-500 hover:text-stone-700">
            Kembali ke Dashboard
        </a>
    </div>

    <h1 class="text-3xl font-bold mb-8">Profil Saya</h1>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PATCH')

        {{-- Foto (opsional) --}}
        @php $me = auth()->user(); @endphp
        <div class="bg-white p-6 rounded-lg border border-stone-200" x-data="{ avatar: null, cover: null }">
            <h2 class="font-bold mb-1">Foto</h2>
            <p class="text-sm text-stone-600 mb-5">Opsional. Foto profil tampil di akun Anda, foto sampul menjadi latar header halaman Akun Saya.</p>

            <div class="flex items-center gap-4">
                <template x-if="avatar"><img :src="avatar" alt="Pratinjau foto profil" class="h-20 w-20 shrink-0 rounded-full object-cover"></template>
                <div x-show="!avatar" class="shrink-0"><x-avatar :user="$me" :size="80" /></div>
                <div class="min-w-0 flex-1">
                    <label for="avatar" class="block text-sm font-medium mb-1.5">Foto profil</label>
                    <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="avatar-help"
                           @change="avatar = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                           class="block w-full text-sm file:mr-3 file:rounded-lg file:border file:border-stone-300 file:bg-white file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-stone-50">
                    <p id="avatar-help" class="mt-1 text-xs text-stone-600">JPG, PNG, atau WebP. Maksimal 2 MB.</p>
                    @if($me->avatar)
                        <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_avatar" value="1"> Hapus foto profil</label>
                    @endif
                </div>
            </div>

            <div class="mt-6 border-t border-stone-100 pt-5">
                <label for="cover" class="block text-sm font-medium mb-1.5">Foto sampul</label>
                @if($me->cover_url)
                    <img src="{{ $me->cover_url }}" alt="Foto sampul saat ini" x-show="!cover" class="mb-3 h-24 w-full rounded-lg object-cover">
                @endif
                <img x-show="cover" x-cloak :src="cover" alt="Pratinjau foto sampul" class="mb-3 h-24 w-full rounded-lg object-cover">
                <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-describedby="cover-help"
                       @change="cover = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                       class="block w-full text-sm file:mr-3 file:rounded-lg file:border file:border-stone-300 file:bg-white file:px-4 file:py-2 file:text-sm file:font-medium hover:file:bg-stone-50">
                <p id="cover-help" class="mt-1 text-xs text-stone-600">JPG, PNG, atau WebP. Maksimal 4 MB. Foto lebar (mis. 1600 x 500) paling pas.</p>
                @if($me->cover)
                    <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_cover" value="1"> Hapus foto sampul</label>
                @endif
            </div>
        </div>

        {{-- Account --}}
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <h2 class="font-bold mb-4">Informasi Akun</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Nama</label>
                    <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">No. Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">
                </div>
            </div>
        </div>

        {{-- Address --}}
        @php $customer = auth()->user()->customer; @endphp
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <div class="mb-4 flex items-center justify-between gap-3"><h2 class="font-bold">Alamat Pengiriman</h2><a href="{{ route('customer.addresses.index') }}" class="text-sm font-medium text-copper-700 hover:underline">Kelola buku alamat</a></div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Alamat Lengkap</label>
                    <textarea name="address" rows="3"
                              class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">{{ old('address', $customer->address ?? '') }}</textarea>
                </div>
                <x-region-select :province-id="old('province_id', $customer->province_id ?? null)" :city-id="old('city_id', $customer->city_id ?? null)" :required="false" />
                <div class="md:w-1/2">
                    <label class="block text-sm font-medium mb-1.5">Kode Pos</label>
                    <input type="text" name="postal_code" inputmode="numeric" maxlength="5" value="{{ old('postal_code', $customer->postal_code ?? '') }}"
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-copper-500 focus:border-copper-500">
                    @error('postal_code') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="submit"
                    class="bg-copper-500 hover:bg-copper-600 text-white px-6 py-3 rounded-lg font-medium transition">
                Simpan Perubahan
            </button>
            <a href="{{ route('customer.dashboard') }}"
               class="px-6 py-3 border border-stone-300 rounded-lg hover:bg-stone-50 text-sm font-medium">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection