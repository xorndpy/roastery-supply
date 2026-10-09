@extends('layouts.app')

@section('title', 'Retur ' . $return->order->order_number)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-10">

    <a href="{{ route('customer.returns.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Retur Saya</a>

    @php
        $stage = $return->stage;
        $label = ['pending' => 'Menunggu tinjauan', 'approved' => 'Disetujui, menunggu refund', 'refunded' => 'Refund selesai', 'rejected' => 'Ditolak'][$stage];
        $color = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-blue-100 text-blue-800', 'refunded' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$stage];
    @endphp

    <div class="flex flex-wrap items-center gap-3 mt-2 mb-6">
        <h1 class="text-2xl font-bold font-mono">{{ $return->order->order_number }}</h1>
        <span class="px-2.5 py-1 rounded text-xs font-medium {{ $color }}">{{ $label }}</span>
    </div>

    @if(session('success'))<div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">{{ session('success') }}</div>@endif

    @if($stage === 'rejected')
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800"><strong>Alasan penolakan:</strong> {{ $return->note }}</div>
    @elseif($stage === 'approved')
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900">
            Refund <strong>Rp {{ number_format($return->refund_amount, 0, ',', '.') }}</strong> akan ditransfer ke rekening di bawah.
            @if($return->note)<div class="mt-1 text-blue-800">Catatan admin: {{ $return->note }}</div>@endif
        </div>
    @elseif($stage === 'refunded')
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-900">
            Refund <strong>Rp {{ number_format($return->refund_amount, 0, ',', '.') }}</strong> sudah ditransfer pada {{ $return->refunded_at->format('d M Y H:i') }}.
        </div>
    @endif

    <div class="bg-white rounded-lg border border-stone-200 p-6 space-y-5">
        <div>
            <div class="text-xs text-stone-500">Alasan</div>
            <div class="font-medium">{{ $return->reason_label }}</div>
            <p class="text-stone-700 mt-2 whitespace-pre-line">{{ $return->reason }}</p>
        </div>

        @if($return->evidence)
            <div>
                <div class="text-xs text-stone-500 mb-2">Foto bukti</div>
                <div class="flex flex-wrap gap-3">
                    @foreach($return->evidence as $i => $path)
                        <a href="{{ route('customer.returns.evidence', [$return->id, $i]) }}" target="_blank">
                            <img src="{{ route('customer.returns.evidence', [$return->id, $i]) }}" alt="Bukti {{ $i + 1 }}" class="h-28 w-28 object-cover rounded border border-stone-200">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="border-t border-stone-100 pt-4 text-sm">
            <div class="text-xs text-stone-500">Rekening refund</div>
            {{ $return->refund_bank }} · {{ $return->refund_account_number }} · a/n {{ $return->refund_account_name }}
        </div>

        <div class="border-t border-stone-100 pt-4 text-sm text-stone-500">
            Diajukan {{ $return->created_at->format('d M Y H:i') }}
            · <a href="{{ route('customer.orders.show', $return->order->order_number) }}" class="text-orange-600 hover:underline">Lihat pesanan</a>
        </div>
    </div>
</div>
@endsection
