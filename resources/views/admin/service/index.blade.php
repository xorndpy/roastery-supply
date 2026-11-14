@extends('layouts.admin')

@section('title', 'Tiket Servis')

@php
    $badge = ['baru' => 'bg-amber-100 text-amber-800', 'diterima' => 'bg-orange-100 text-orange-800', 'diperiksa' => 'bg-orange-200 text-orange-900', 'menunggu_persetujuan' => 'bg-yellow-100 text-yellow-800', 'dikerjakan' => 'bg-stone-200 text-stone-800', 'selesai' => 'bg-green-100 text-green-800', 'ditolak' => 'bg-red-100 text-red-800', 'dibatalkan' => 'bg-stone-100 text-stone-600'];
@endphp

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Tiket Servis</h1>
    <p class="mt-1 text-sm text-stone-500">Pengajuan servis dari pelanggan, termasuk yang masih dalam garansi.</p>
</div>

<div class="mb-4 flex flex-wrap gap-2 text-sm">
    <a href="{{ route('admin.service.index') }}" class="rounded-full border px-3 py-1.5 {{ request('status') ? 'border-stone-300 text-stone-600' : 'border-stone-900 bg-stone-900 text-white' }}">Semua</a>
    @foreach(\App\Models\ServiceTicket::STATUSES as $k => $label)
        <a href="{{ route('admin.service.index', ['status' => $k]) }}" class="rounded-full border px-3 py-1.5 {{ request('status') === $k ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300 text-stone-600' }}">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
</div>

<form method="GET" class="mb-6 flex flex-wrap gap-3 rounded-lg border border-stone-200 bg-white p-4">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari no. tiket, produk, nomor seri, pelanggan" class="min-w-[220px] flex-1 rounded-lg border border-stone-300 px-3 py-2">
    <select name="warranty" class="rounded-lg border border-stone-300 px-3 py-2"><option value="">Semua</option><option value="1" @selected(request('warranty') === '1')>Masih garansi</option><option value="0" @selected(request('warranty') === '0')>Di luar garansi</option></select>
    <button class="rounded-lg bg-stone-900 px-5 py-2 text-white">Cari</button>
</form>

<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Tiket</th><th class="px-4 py-3">Pelanggan</th><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Garansi</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Dibuat</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($tickets as $t)
            <tr class="hover:bg-stone-50">
                <td class="px-4 py-3"><a href="{{ route('admin.service.show', $t->id) }}" class="font-mono text-stone-900 hover:underline">{{ $t->ticket_number }}</a></td>
                <td class="px-4 py-3">{{ $t->user->name ?? '-' }}</td>
                <td class="px-4 py-3">{{ $t->product_name }}@if($t->serial_number)<div class="text-xs text-stone-500">SN {{ $t->serial_number }}</div>@endif<div class="text-xs text-stone-500">{{ $t->issueLabel() }}</div></td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $t->in_warranty ? 'bg-green-100 text-green-800' : 'bg-stone-100 text-stone-600' }}">{{ $t->in_warranty ? 'Garansi' : 'Berbayar' }}</span></td>
                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs {{ $badge[$t->status] ?? 'bg-stone-100' }}">{{ $t->statusLabel() }}</span></td>
                <td class="whitespace-nowrap px-4 py-3 text-stone-600">{{ wib($t->created_at, 'd M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada tiket.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endsection
