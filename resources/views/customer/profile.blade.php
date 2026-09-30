@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <div class="mb-6">
        <a href="{{ route('customer.dashboard') }}" class="text-sm text-stone-500 hover:text-stone-700">
            ← Kembali ke Dashboard
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

    <form method="POST" action="{{ route('customer.profile.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')

        {{-- Account --}}
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <h2 class="font-bold mb-4">Informasi Akun</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Nama</label>
                    <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">No. Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                           class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>
        </div>

        {{-- Address --}}
        @php $customer = auth()->user()->customer; @endphp
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <h2 class="font-bold mb-4">Alamat Pengiriman</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">Alamat Lengkap</label>
                    <textarea name="address" rows="3"
                              class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">{{ old('address', $customer->address ?? '') }}</textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Kota</label>
                        <input type="text" name="city" value="{{ old('city', $customer->city ?? '') }}"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Provinsi</label>
                        <input type="text" name="province" value="{{ old('province', $customer->province ?? '') }}"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Kode Pos</label>
                        <input type="text" name="postal_code" value="{{ old('postal_code', $customer->postal_code ?? '') }}"
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 focus:ring-orange-500 focus:border-orange-500">
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="submit"
                    class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
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