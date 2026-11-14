@extends('layouts.shop')

@section('title', 'Tiket ' . $ticket->ticket_number)

@section('content')
<x-page-header plain :title="'Tiket ' . $ticket->ticket_number" :crumbs="[['Beranda', route('home')], ['Garansi & Servis', route('customer.service.index')], [$ticket->ticket_number]]" />

<div class="container-x py-10 space-y-6">
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

    <div class="rounded-lg border border-stone-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium text-stone-900">{{ $ticket->product_name }}@if($ticket->serial_number) <span class="text-stone-500">· SN {{ $ticket->serial_number }}</span>@endif</p>
                <p class="text-sm text-stone-600">{{ $ticket->issueLabel() }} · dibuat {{ wib($ticket->created_at, 'd M Y, H:i') }}</p>
            </div>
            <div class="text-right">
                <span class="rounded-full bg-orange-100 px-3 py-1 text-sm text-orange-800">{{ $ticket->statusLabel() }}</span>
                <p class="mt-1 text-xs {{ $ticket->in_warranty ? 'text-green-700' : 'text-stone-500' }}">{{ $ticket->in_warranty ? 'Masih garansi: tanpa biaya perbaikan' : 'Di luar garansi: ada biaya perbaikan' }}</p>
            </div>
        </div>
        <p class="mt-4 whitespace-pre-line text-sm text-stone-700">{{ $ticket->description }}</p>
        @if($ticket->photos)
            <div class="mt-3 flex gap-2">@foreach($ticket->photos as $i => $p)<a href="{{ route('customer.service.photo', [$ticket->ticket_number, $i + 1]) }}" target="_blank" rel="noopener" class="text-xs text-orange-700 underline">Foto {{ $i + 1 }}</a>@endforeach</div>
        @endif
    </div>

    @if($ticket->status === 'menunggu_persetujuan')
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 p-5">
            <p class="font-medium text-stone-900">Estimasi biaya perbaikan: Rp {{ number_format($ticket->estimate_cost, 0, ',', '.') }}</p>
            <p class="mt-1 text-sm text-stone-700">Perbaikan baru dikerjakan setelah kamu menyetujui biaya ini.</p>
            <div class="mt-3 flex gap-3">
                <form method="POST" action="{{ route('customer.service.respond', $ticket->ticket_number) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="rounded-lg bg-orange-500 px-5 py-2 text-sm font-medium text-white hover:bg-orange-600">Setujui biaya</button></form>
                <form method="POST" action="{{ route('customer.service.respond', $ticket->ticket_number) }}" data-confirm-title="Tolak estimasi biaya?" data-confirm-text="Tiket akan dibatalkan." data-confirm-ok="Ya, tolak" data-confirm-tone="danger">@csrf<input type="hidden" name="decision" value="decline"><button class="rounded-lg border border-stone-300 px-5 py-2 text-sm">Tolak</button></form>
            </div>
        </div>
    @endif

    @if($ticket->status === 'selesai' && (float) $ticket->final_cost > 0)
        <div class="rounded-lg border border-stone-200 bg-white p-5 text-sm">Biaya servis: <strong>Rp {{ number_format($ticket->final_cost, 0, ',', '.') }}</strong> · {{ $ticket->paid_at ? 'sudah dibayar' : 'bayar saat pengambilan/hubungi toko' }}</div>
    @endif

    <div class="rounded-lg border border-stone-200 bg-white p-5">
        <h3 class="mb-4 font-semibold text-stone-900">Riwayat</h3>
        <ol class="relative ml-2 space-y-5 border-l border-stone-200">
            @foreach($events->reverse() as $e)
                <li class="ml-5">
                    <span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full {{ $loop->first ? 'bg-orange-500 ring-4 ring-orange-100' : 'bg-stone-400' }}"></span>
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3"><p class="text-sm font-medium">{{ \App\Models\ServiceTicket::STATUSES[$e->status] ?? $e->status }}</p><p class="text-xs text-stone-500">{{ wib($e->created_at, 'd M Y, H:i') }}</p></div>
                    @if($e->note)<p class="mt-0.5 text-xs text-stone-600">{{ $e->note }}</p>@endif
                </li>
            @endforeach
        </ol>
    </div>

    @if(in_array($ticket->status, ['baru', 'menunggu_persetujuan'], true))
        <form method="POST" action="{{ route('customer.service.cancel', $ticket->ticket_number) }}" data-confirm-title="Batalkan tiket ini?" data-confirm-ok="Ya, batalkan" data-confirm-tone="danger">@csrf<button class="text-sm text-red-600 underline">Batalkan tiket</button></form>
    @endif
</div>
@endsection
