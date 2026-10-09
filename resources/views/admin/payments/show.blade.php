@extends('layouts.admin')

@section('title', 'Pembayaran ' . $payment->order->order_number)

@section('content')

@php
    $order = $payment->order;
    $badge = [
        'pending'  => 'bg-yellow-100 text-yellow-800',
        'verified' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
    ][$payment->status] ?? 'bg-stone-100 text-stone-700';
    $label = ['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'][$payment->status] ?? $payment->status;
    $methodLabel = ['transfer_bca' => 'Transfer BCA', 'transfer_mandiri' => 'Transfer Mandiri', 'transfer_bni' => 'Transfer BNI'][$payment->method] ?? $payment->method;
@endphp

<div class="mb-6">
    <a href="{{ route('admin.payments.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Pembayaran</a>
    <div class="flex flex-wrap items-center gap-3 mt-2">
        <h1 class="text-2xl font-bold text-stone-900 font-mono">{{ $order->order_number }}</h1>
        <span class="px-2.5 py-1 rounded text-xs font-medium {{ $badge }}">{{ $label }}</span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Kiri: bukti + aksi --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <h2 class="font-bold mb-4">Bukti Pembayaran</h2>
            @if($payment->proof_image)
                <a href="{{ route('admin.payments.proof', $payment->id) }}" target="_blank">
                    <img src="{{ route('admin.payments.proof', $payment->id) }}" alt="Bukti transfer"
                         class="max-h-[32rem] rounded border border-stone-200">
                </a>
            @else
                <p class="text-stone-500 text-sm">Tidak ada file bukti.</p>
            @endif
        </div>

        @if($payment->status === 'pending')
            @canany(['verify_payments', 'reject_payments'])
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-1">Verifikasi</h2>
                <p class="text-sm text-stone-500 mb-4">
                    Pastikan nominal <strong>Rp {{ number_format($payment->amount, 0, ',', '.') }}</strong> sudah masuk ke rekening.
                    Verifikasi akan mengurangi stok.
                </p>
                <div class="flex flex-wrap gap-6 items-start">
                    @can('verify_payments')
                    <form method="POST" action="{{ route('admin.payments.verify', $payment->id) }}">
                        @csrf
                        <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-lg font-medium">Verifikasi Pembayaran</button>
                    </form>
                    @endcan
                    @can('reject_payments')
                    <form method="POST" action="{{ route('admin.payments.reject', $payment->id) }}" class="flex-1 min-w-[260px] space-y-2">
                        @csrf
                        <textarea name="note" rows="2" maxlength="500" placeholder="Alasan penolakan (dilihat customer)"
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></textarea>
                        <button class="border border-red-300 text-red-700 hover:bg-red-50 px-5 py-2 rounded-lg text-sm">Tolak</button>
                    </form>
                    @endcan
                </div>
            </div>
            @endcanany
        @endif
    </div>

    {{-- Kanan: ringkasan --}}
    <div class="space-y-6">
        <div class="bg-white p-6 rounded-lg border border-stone-200 text-sm space-y-2">
            <h2 class="font-bold mb-2">Pembayaran</h2>
            <div class="flex justify-between"><span class="text-stone-500">Metode</span><span>{{ $methodLabel }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Jumlah</span><span class="font-bold">Rp {{ number_format($payment->amount, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Diunggah</span><span>{{ $payment->created_at->format('d M Y H:i') }}</span></div>
            @if($payment->verified_at)
                <div class="flex justify-between"><span class="text-stone-500">Diproses</span><span>{{ $payment->verified_at->format('d M Y H:i') }}</span></div>
                <div class="flex justify-between"><span class="text-stone-500">Oleh</span><span>{{ $payment->verifier->name ?? '-' }}</span></div>
            @endif
            @if($payment->note)
                <div class="pt-2 border-t border-stone-100"><div class="text-stone-500">Catatan</div><div>{{ $payment->note }}</div></div>
            @endif
        </div>

        <div class="bg-white p-6 rounded-lg border border-stone-200 text-sm space-y-2">
            <h2 class="font-bold mb-2">Pesanan</h2>
            <div class="flex justify-between"><span class="text-stone-500">Status</span><span class="capitalize">{{ $order->status }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Total</span><span class="font-bold">Rp {{ number_format($order->total, 0, ',', '.') }}</span></div>
            @if((float) $payment->amount !== (float) $order->total)
                <div class="p-2 bg-amber-50 border border-amber-200 text-amber-800 rounded text-xs">Nominal pembayaran berbeda dengan total pesanan.</div>
            @endif
            <div class="pt-2 border-t border-stone-100">
                <div class="font-medium">{{ $order->shipping_name ?: ($order->customer->name ?? '-') }}</div>
                <div class="text-stone-500">{{ $order->shipping_phone ?: ($order->customer->phone ?? '') }}</div>
                <div class="text-stone-500">{{ $order->full_shipping_address ?: ($order->customer->address ?? '-') }}</div>
            </div>
            <ul class="pt-2 border-t border-stone-100 space-y-1">
                @foreach($order->items as $item)
                    <li class="flex justify-between gap-3"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span></li>
                @endforeach
            </ul>
            @can('view_orders')
                <a href="{{ route('admin.orders.show', $order->id) }}" class="inline-block pt-2 text-orange-600 hover:underline">Buka pesanan →</a>
            @endcan
        </div>
    </div>
</div>

@endsection
