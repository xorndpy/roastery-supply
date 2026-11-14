@extends('layouts.shop')

@section('title', 'Ajukan Servis')

@section('content')
<x-page-header plain title="Ajukan Servis" :crumbs="[['Beranda', route('home')], ['Garansi & Servis', route('customer.service.index')], ['Ajukan']]" />

<div class="container-x py-10">
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('customer.service.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-5 rounded-lg border border-stone-200 bg-white p-6" x-data="{ w: '{{ old('warranty_id', $selected ?: '') }}' }">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-medium">Unit yang bermasalah</label>
            <select name="warranty_id" x-model="w" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                <option value="">Tidak ada di daftar / di luar garansi</option>
                @foreach($warranties as $w)
                    <option value="{{ $w->id }}">{{ $w->product_name }}{{ $w->serial_number ? ' · SN ' . $w->serial_number : '' }} ({{ $w->isActive() ? 'garansi sampai ' . $w->expires_at->format('d M Y') : 'garansi berakhir' }})</option>
                @endforeach
            </select>
        </div>

        <div x-show="!w" x-cloak class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1.5 block text-sm font-medium">Nama produk</label><input type="text" name="product_name" value="{{ old('product_name') }}" maxlength="150" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
            <div><label class="mb-1.5 block text-sm font-medium">Nomor seri (bila ada)</label><input type="text" name="serial_number" value="{{ old('serial_number') }}" maxlength="60" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Jenis masalah *</label>
            <select name="issue_type" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
                <option value="">Pilih</option>
                @foreach(\App\Models\ServiceTicket::ISSUE_TYPES as $k => $label)<option value="{{ $k }}" @selected(old('issue_type') === $k)>{{ $label }}</option>@endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium">Ceritakan masalahnya *</label>
            <textarea name="description" rows="5" required minlength="10" maxlength="2000" placeholder="Kapan mulai terjadi, apa yang terlihat/terdengar, sudah dicoba apa saja" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('description') }}</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1.5 block text-sm font-medium">Nomor yang bisa dihubungi *</label><input type="text" name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone ?? '') }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
            <div><label class="mb-1.5 block text-sm font-medium">Foto (maks. 3, JPG/PNG)</label><input type="file" name="photos[]" accept=".jpg,.jpeg,.png" multiple class="w-full text-sm"></div>
        </div>

        <div class="flex gap-3">
            <button class="rounded-lg bg-orange-500 px-6 py-2.5 font-medium text-white hover:bg-orange-600">Kirim pengajuan</button>
            <a href="{{ route('customer.service.index') }}" class="rounded-lg border border-stone-300 px-6 py-2.5 text-stone-700">Batal</a>
        </div>
    </form>
</div>
@endsection
