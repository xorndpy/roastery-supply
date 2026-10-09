@extends('layouts.admin')

@section('title', 'Retur')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Retur & Refund</h1>
    <p class="text-sm text-stone-500 mt-1">Retur diajukan customer maksimal {{ \App\Services\ReturnService::WINDOW_DAYS }} hari setelah pesanan selesai. Persetujuan manual.</p>
</div>

@php
    $tabs = ['pending' => 'Menunggu', 'approved' => 'Perlu refund', 'refunded' => 'Refund selesai', 'rejected' => 'Ditolak', 'all' => 'Semua'];
@endphp

<div class="flex flex-wrap gap-2 mb-4 border-b border-stone-200">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.returns.index', ['tab' => $key]) }}"
           class="px-4 py-2 -mb-px border-b-2 {{ $tab === $key ? 'border-orange-500 text-stone-900 font-medium' : 'border-transparent text-stone-500 hover:text-stone-700' }}">
            {{ $label }} <span class="text-xs text-stone-400">({{ $counts[$key] }})</span>
        </a>
    @endforeach
</div>

<form method="GET" class="mb-4 flex gap-3">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari no. pesanan / nama" class="flex-1 max-w-sm border border-stone-300 rounded-lg px-3 py-2">
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Cari</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Pesanan</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Alasan</th><th class="px-4 py-3 text-right">Nominal</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Diajukan</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($returns as $r)
            @php
                $stage = $r->stage;
                $label = ['pending' => 'Menunggu', 'approved' => 'Perlu refund', 'refunded' => 'Refund selesai', 'rejected' => 'Ditolak'][$stage];
                $color = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-blue-100 text-blue-800', 'refunded' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$stage];
            @endphp
            <tr>
                <td class="px-4 py-3"><a href="{{ route('admin.returns.show', $r->id) }}" class="font-mono font-medium text-orange-600 hover:underline">{{ $r->order->order_number }}</a></td>
                <td class="px-4 py-3">{{ $r->order->shipping_name ?: ($r->order->customer->name ?? '-') }}</td>
                <td class="px-4 py-3 text-stone-600">{{ $r->reason_label }}</td>
                <td class="px-4 py-3 text-right">Rp {{ number_format($r->refund_amount, 0, ',', '.') }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $color }}">{{ $label }}</span></td>
                <td class="px-4 py-3 text-stone-500">{{ $r->created_at->format('d M Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Tidak ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $returns->links() }}</div>
@endsection
