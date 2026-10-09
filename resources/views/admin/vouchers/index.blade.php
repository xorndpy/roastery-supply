@extends('layouts.admin')

@section('title', 'Voucher')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Voucher</h1>
        <p class="text-sm text-stone-500 mt-1">Satu kode per pesanan, tidak bisa digabung. Kuota dikembalikan jika pesanan dibatalkan.</p>
    </div>
    @can('create_vouchers')<a href="{{ route('admin.vouchers.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Buat Voucher</a>@endcan
</div>

@php
    $stateLabels = ['aktif' => 'Aktif', 'terjadwal' => 'Terjadwal', 'berakhir' => 'Berakhir', 'habis' => 'Kuota habis', 'nonaktif' => 'Nonaktif'];
    $stateColors = ['aktif' => 'bg-green-100 text-green-700', 'terjadwal' => 'bg-blue-100 text-blue-700', 'berakhir' => 'bg-red-100 text-red-700', 'habis' => 'bg-amber-100 text-amber-700', 'nonaktif' => 'bg-stone-100 text-stone-600'];
@endphp

<div class="flex flex-wrap gap-2 mb-4 text-sm">
    <a href="{{ route('admin.vouchers.index') }}" class="px-3 py-1.5 rounded-full border {{ request('state') ? 'border-stone-300 text-stone-600' : 'border-stone-900 bg-stone-900 text-white' }}">Semua</a>
    @foreach($stateLabels as $key => $label)
        <a href="{{ route('admin.vouchers.index', ['state' => $key]) }}" class="px-3 py-1.5 rounded-full border {{ request('state') === $key ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300 text-stone-600' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
    @endforeach
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex gap-3">
    <input type="hidden" name="state" value="{{ request('state') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari kode / deskripsi" class="flex-1 border border-stone-300 rounded-lg px-3 py-2">
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Cari</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Diskon</th><th class="px-4 py-3">Min. belanja</th><th class="px-4 py-3">Pemakaian</th><th class="px-4 py-3">Masa berlaku</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($vouchers as $v)
            <tr>
                <td class="px-4 py-3">
                    <div class="font-mono font-bold">{{ $v->code }}</div>
                    @if($v->description)<div class="text-xs text-stone-500">{{ $v->description }}</div>@endif
                </td>
                <td class="px-4 py-3">
                    @if($v->type === 'percentage')
                        {{ rtrim(rtrim(number_format($v->value, 2, '.', ''), '0'), '.') }}%
                        @if($v->max_discount)<div class="text-xs text-stone-500">maks Rp {{ number_format($v->max_discount, 0, ',', '.') }}</div>@endif
                    @else
                        Rp {{ number_format($v->value, 0, ',', '.') }}
                    @endif
                </td>
                <td class="px-4 py-3">{{ $v->min_purchase > 0 ? 'Rp ' . number_format($v->min_purchase, 0, ',', '.') : '-' }}</td>
                <td class="px-4 py-3">
                    {{ $v->used_count }} / {{ $v->quota > 0 ? $v->quota : '∞' }}
                    <div class="text-xs text-stone-500">{{ $v->per_user_limit > 0 ? $v->per_user_limit . '× per user' : 'tanpa batas per user' }}</div>
                </td>
                <td class="px-4 py-3 text-stone-600 text-xs">
                    {{ $v->start_at?->format('d M Y') ?? 'sekarang' }} → {{ $v->end_at?->format('d M Y') ?? 'tanpa batas' }}
                </td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $stateColors[$v->state] }}">{{ $stateLabels[$v->state] }}</span></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @can('edit_vouchers')<a href="{{ route('admin.vouchers.edit', $v->id) }}" class="text-orange-600 hover:underline mr-3">Edit</a>@endcan
                    @can('delete_vouchers')
                    <form method="POST" action="{{ route('admin.vouchers.destroy', $v->id) }}" class="inline" onsubmit="return confirm('Hapus voucher {{ $v->code }}?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-stone-500">Belum ada voucher.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
