@extends('layouts.admin')

@section('title', 'Tambah Layanan Pengiriman')

@section('content')

<div class="mb-6"><a href="{{ route('admin.shipping.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Tarif Pengiriman</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('admin.shipping.store') }}" class="max-w-xl bg-white p-6 rounded-lg border border-stone-200 space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium mb-1.5">Nama layanan *</label>
        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="Kargo Reguler" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Keterangan</label>
        <input type="text" name="description" value="{{ old('description') }}" maxlength="255" placeholder="Diantar ekspedisi kargo, asuransi termasuk" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Urutan tampil</label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}" class="w-32 border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">Buat & Isi Tarif</button>
</form>
@endsection
