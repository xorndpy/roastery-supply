@extends('layouts.admin')

@section('title', 'Tarif Pengiriman')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Tarif Pengiriman</h1>
        <p class="text-sm text-stone-500 mt-1">Layanan dan tarif per wilayah yang ditawarkan di checkout.</p>
    </div>
    @can('manage_shipping_rates')<a href="{{ route('admin.shipping.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Tambah Layanan</a>@endcan
</div>

@if($methods->where('is_active', true)->where('rates_count', '>', 0)->isEmpty())
    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg text-sm">
        <strong>Ongkir belum diatur.</strong> Selama belum ada layanan aktif yang punya tarif, checkout berjalan <em>tanpa ongkir</em> (Rp 0, dikonfirmasi manual).
        Buat layanan dan isi tarifnya agar ongkir dihitung otomatis.
    </div>
@endif

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Layanan</th><th class="px-4 py-3">Tarif</th><th class="px-4 py-3">Urutan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($methods as $m)
            <tr>
                <td class="px-4 py-3"><div class="font-medium">{{ $m->name }}</div>@if($m->description)<div class="text-xs text-stone-500">{{ $m->description }}</div>@endif</td>
                <td class="px-4 py-3">{{ $m->rates_count }} tarif wilayah</td>
                <td class="px-4 py-3">{{ $m->sort_order }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $m->is_active ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-600' }}">{{ $m->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('admin.shipping.edit', $m->id) }}" class="text-orange-600 hover:underline">Atur tarif</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Belum ada layanan pengiriman.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<p class="text-xs text-stone-500 mt-4">Rumus: ongkir = tarif per kg × berat tagihan (dibulatkan ke atas, minimal 1 kg), tidak kurang dari tarif minimum. Tarif kota mengalahkan tarif provinsi.</p>
@endsection
