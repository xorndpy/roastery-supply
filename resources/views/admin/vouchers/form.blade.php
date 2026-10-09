@extends('layouts.admin')

@php
    $isEdit = $voucher->exists;
    $locked = $isEdit && $voucher->used_count > 0;
@endphp
@section('title', $isEdit ? 'Edit Voucher ' . $voucher->code : 'Buat Voucher')

@section('content')

<div class="mb-6"><a href="{{ route('admin.vouchers.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Voucher</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.vouchers.update', $voucher->id) : route('admin.vouchers.store') }}"
      class="max-w-2xl bg-white p-6 rounded-lg border border-stone-200 space-y-5" x-data="{ type: '{{ old('type', $voucher->type) }}' }">
    @csrf @if($isEdit) @method('PUT') @endif

    @if($locked)
        <div class="p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
            Voucher ini sudah dipakai {{ $voucher->used_count }}×. Kode dan jenis diskon dikunci agar riwayat pesanan tetap konsisten.
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Kode *</label>
            <input type="text" name="code" value="{{ old('code', $voucher->code) }}" required maxlength="50" {{ $locked ? 'readonly' : '' }}
                   placeholder="HEMAT10" class="w-full border border-stone-300 rounded-lg px-3 py-2 font-mono uppercase {{ $locked ? 'bg-stone-50 text-stone-500' : '' }}">
            <p class="text-xs text-stone-500 mt-1">Huruf/angka, tanpa spasi. Tidak peka huruf besar/kecil saat dipakai.</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Deskripsi</label>
            <input type="text" name="description" value="{{ old('description', $voucher->description) }}" maxlength="255" placeholder="Promo kemerdekaan"
                   class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Jenis diskon *</label>
            <select name="type" x-model="type" {{ $locked ? 'disabled' : '' }} class="w-full border border-stone-300 rounded-lg px-3 py-2">
                <option value="percentage">Persentase (%)</option>
                <option value="fixed">Nominal (Rp)</option>
            </select>
            @if($locked)<input type="hidden" name="type" value="{{ $voucher->type }}">@endif
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5"><span x-show="type === 'percentage'">Persentase (1–100) *</span><span x-show="type === 'fixed'" x-cloak>Nominal diskon (Rp) *</span></label>
            <input type="number" name="value" step="1" min="1" value="{{ old('value', $voucher->exists ? (int) $voucher->value : '') }}" required class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div x-show="type === 'percentage'">
            <label class="block text-sm font-medium mb-1.5">Diskon maksimum (Rp)</label>
            <input type="number" name="max_discount" min="0" value="{{ old('max_discount', $voucher->max_discount ? (int) $voucher->max_discount : '') }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
            <p class="text-xs text-stone-500 mt-1">Batas atas potongan. Kosongkan jika tanpa batas.</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Minimum belanja (Rp)</label>
            <input type="number" name="min_purchase" min="0" value="{{ old('min_purchase', (int) $voucher->min_purchase) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Kuota total</label>
            <input type="number" name="quota" min="0" value="{{ old('quota', $voucher->quota ?? 0) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
            <p class="text-xs text-stone-500 mt-1">0 = tanpa batas.</p>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Batas pakai per user</label>
            <input type="number" name="per_user_limit" min="0" value="{{ old('per_user_limit', $voucher->per_user_limit ?? 1) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
            <p class="text-xs text-stone-500 mt-1">0 = tanpa batas. Standarnya 1× per user.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Mulai berlaku</label>
            <input type="datetime-local" name="start_at" value="{{ old('start_at', $voucher->start_at?->format('Y-m-d\TH:i')) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Berakhir</label>
            <input type="datetime-local" name="end_at" value="{{ old('end_at', $voucher->end_at?->format('Y-m-d\TH:i')) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $voucher->is_active))> Aktif</label>

    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">{{ $isEdit ? 'Simpan' : 'Buat Voucher' }}</button>
</form>
@endsection
