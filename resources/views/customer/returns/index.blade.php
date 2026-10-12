@extends('layouts.app')

@section('title', 'Retur Saya')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
        <div>
            <h1 class="text-3xl font-bold">Retur Saya</h1>
            <p class="text-sm text-stone-500 mt-1">Retur bisa diajukan maksimal {{ \App\Services\ReturnService::WINDOW_DAYS }} hari setelah pesanan selesai.</p>
        </div>
        <a href="{{ route('customer.returns.create') }}" class="bg-stone-900 hover:bg-stone-800 text-white px-5 py-2.5 rounded-lg text-sm font-medium">Ajukan Retur</a>
    </div>

    @if(session('success'))<div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-lg border border-stone-200 divide-y divide-stone-100">
    @forelse($returns as $r)
        @php
            $stage = $r->stage;
            $label = ['pending' => 'Menunggu tinjauan', 'approved' => 'Disetujui, menunggu refund', 'refunded' => 'Refund selesai', 'rejected' => 'Ditolak'][$stage];
            $color = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-orange-100 text-orange-800', 'refunded' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$stage];
        @endphp
        <a href="{{ route('customer.returns.show', $r->id) }}" class="flex flex-wrap justify-between gap-3 p-5 hover:bg-stone-50">
            <div>
                <div class="font-mono font-medium">{{ $r->order->order_number }}</div>
                <div class="text-sm text-stone-500 mt-0.5">{{ $r->reason_label }} · diajukan {{ $r->created_at->format('d M Y') }}</div>
            </div>
            <span class="self-start px-2.5 py-1 rounded text-xs font-medium {{ $color }}">{{ $label }}</span>
        </a>
    @empty
        <div class="p-10 text-center text-stone-500">Belum ada pengajuan retur.</div>
    @endforelse
    </div>

    <div class="mt-4">{{ $returns->links() }}</div>
</div>
@endsection
