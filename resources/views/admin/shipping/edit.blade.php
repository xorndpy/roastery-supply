@extends('layouts.admin')

@section('title', 'Tarif ' . $method->name)

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.shipping.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Tarif Pengiriman</a>
    <h1 class="text-2xl font-bold text-stone-900 mt-2">{{ $method->name }}</h1>
</div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@can('manage_shipping_rates')
<form method="POST" action="{{ route('admin.shipping.update', $method->id) }}" class="bg-white p-6 rounded-lg border border-stone-200 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
    @csrf @method('PUT')
    <div class="md:col-span-2"><label class="block text-sm font-medium mb-1.5">Nama</label><input type="text" name="name" value="{{ old('name', $method->name) }}" required maxlength="100" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <div><label class="block text-sm font-medium mb-1.5">Urutan</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $method->sort_order) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <label class="flex items-center gap-2 text-sm pb-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $method->is_active))> Aktif</label>
    <div class="md:col-span-3"><label class="block text-sm font-medium mb-1.5">Keterangan</label><input type="text" name="description" value="{{ old('description', $method->description) }}" maxlength="255" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <button class="bg-stone-900 text-white px-5 py-2.5 rounded-lg">Simpan</button>
</form>
@endcan

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- Daftar tarif --}}
    <div class="xl:col-span-2 bg-white rounded-lg border border-stone-200 overflow-x-auto">
        <div class="p-4 border-b border-stone-100 flex flex-wrap justify-between gap-2">
            <h2 class="font-bold">Tarif wilayah</h2>
            <span class="text-sm text-stone-500">{{ $coveredProvinces }} dari {{ $totalProvinces }} provinsi punya tarif</span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Wilayah</th><th class="px-4 py-3 text-right">Per kg</th><th class="px-4 py-3 text-right">Minimum</th><th class="px-4 py-3">Estimasi</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-stone-100">
            @forelse($rates as $r)
                <tr>
                    <td class="px-4 py-2.5">
                        @if($r->city)<span class="text-stone-400">↳</span> {{ $r->city->name }} <span class="text-xs text-stone-400">({{ $r->province->name }})</span>
                        @else<strong>{{ $r->province->name }}</strong> <span class="text-xs text-stone-400">semua kota</span>@endif
                    </td>
                    <td class="px-4 py-2.5 text-right">Rp {{ number_format($r->rate_per_kg, 0, ',', '.') }}</td>
                    <td class="px-4 py-2.5 text-right">Rp {{ number_format($r->min_charge, 0, ',', '.') }}</td>
                    <td class="px-4 py-2.5 text-stone-500">{{ $r->etd_label ?? '-' }}</td>
                    <td class="px-4 py-2.5 text-right">
                        @can('manage_shipping_rates')
                        <form method="POST" action="{{ route('admin.shipping.rates.destroy', [$method->id, $r->id]) }}" onsubmit="return confirm('Hapus tarif ini?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button></form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Belum ada tarif. Pakai "Isi semua provinsi" di samping untuk memulai cepat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @can('manage_shipping_rates')
    <div class="space-y-6">

        {{-- Tambah / ubah satu tarif --}}
        <form method="POST" action="{{ route('admin.shipping.rates.store', $method->id) }}" class="bg-white p-5 rounded-lg border border-stone-200 space-y-3">
            @csrf
            <h2 class="font-bold">Tambah / ubah tarif</h2>
            <p class="text-xs text-stone-500">Kosongkan kota untuk berlaku di seluruh provinsi. Memilih kota membuat pengecualian untuk kota itu. Jika tarif untuk wilayah ini sudah ada, nilainya diperbarui.</p>
            <x-region-select :required="false" />
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs mb-1">Tarif per kg (Rp) *</label><input type="number" name="rate_per_kg" min="0" step="500" required class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs mb-1">Minimum (Rp)</label><input type="number" name="min_charge" min="0" step="1000" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs mb-1">Estimasi min (hari)</label><input type="number" name="etd_min" min="1" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs mb-1">Estimasi maks (hari)</label><input type="number" name="etd_max" min="1" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <button class="bg-stone-900 text-white px-5 py-2 rounded-lg text-sm">Simpan Tarif</button>
        </form>

        {{-- Isi semua provinsi --}}
        <form method="POST" action="{{ route('admin.shipping.rates.bulk', $method->id) }}" class="bg-white p-5 rounded-lg border border-stone-200 space-y-3" onsubmit="return confirm('Terapkan tarif ini ke semua provinsi?')">
            @csrf
            <h2 class="font-bold">Isi semua provinsi</h2>
            <p class="text-xs text-stone-500">Cara cepat memulai: satu tarif untuk 38 provinsi, lalu ubah provinsi/kota tertentu di atas.</p>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs mb-1">Tarif per kg (Rp) *</label><input type="number" name="rate_per_kg" min="0" step="500" required class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs mb-1">Minimum (Rp)</label><input type="number" name="min_charge" min="0" step="1000" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs mb-1">Estimasi min (hari)</label><input type="number" name="etd_min" min="1" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs mb-1">Estimasi maks (hari)</label><input type="number" name="etd_max" min="1" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <label class="flex items-start gap-2 text-xs"><input type="checkbox" name="overwrite" value="1" class="mt-0.5"> Timpa tarif provinsi yang sudah ada (tanpa ini, provinsi yang sudah punya tarif dilewati)</label>
            <button class="border border-stone-300 hover:bg-stone-50 px-5 py-2 rounded-lg text-sm">Terapkan ke Semua Provinsi</button>
        </form>

        <form method="POST" action="{{ route('admin.shipping.destroy', $method->id) }}" onsubmit="return confirm('Hapus layanan {{ $method->name }} beserta tarifnya?')">
            @csrf @method('DELETE')
            <button class="text-sm text-red-600 hover:underline">Hapus layanan ini</button>
        </form>
    </div>
    @endcan
</div>
@endsection
