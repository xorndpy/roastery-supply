@extends('layouts.admin')

@section('title', 'Retur ' . $return->order->order_number)

@section('content')

@php
    $order = $return->order;
    $stage = $return->stage;
    $label = ['pending' => 'Menunggu tinjauan', 'approved' => 'Perlu refund', 'refunded' => 'Refund selesai', 'rejected' => 'Ditolak'][$stage];
    $color = ['pending' => 'bg-yellow-100 text-yellow-800', 'approved' => 'bg-blue-100 text-blue-800', 'refunded' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800'][$stage];
@endphp

<div class="mb-6">
    <a href="{{ route('admin.returns.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Retur</a>
    <div class="flex flex-wrap items-center gap-3 mt-2">
        <h1 class="text-2xl font-bold font-mono">{{ $order->order_number }}</h1>
        <span class="px-2.5 py-1 rounded text-xs font-medium {{ $color }}">{{ $label }}</span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">

        <div class="bg-white p-6 rounded-lg border border-stone-200 space-y-4">
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
                            <a href="{{ route('admin.returns.evidence', [$return->id, $i]) }}" target="_blank">
                                <img src="{{ route('admin.returns.evidence', [$return->id, $i]) }}" alt="Bukti {{ $i + 1 }}" class="h-32 w-32 object-cover rounded border border-stone-200">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        @if($stage === 'pending')
            <div class="bg-white p-6 rounded-lg border border-stone-200 grid grid-cols-1 md:grid-cols-2 gap-8">
                @can('approve_returns')
                <form method="POST" action="{{ route('admin.returns.approve', $return->id) }}" class="space-y-3">
                    @csrf
                    <h2 class="font-bold">Setujui</h2>
                    <div>
                        <label class="block text-sm mb-1.5">Nominal refund (Rp)</label>
                        <input type="number" name="refund_amount" min="0" max="{{ (int) $order->total }}" value="{{ old('refund_amount', (int) $order->total) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
                        <p class="text-xs text-stone-500 mt-1">Maksimal total pesanan Rp {{ number_format($order->total, 0, ',', '.') }}.</p>
                    </div>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="restock" value="1" class="mt-0.5"> <span>Barang layak jual, kembalikan ke stok</span></label>
                    <textarea name="note" rows="2" maxlength="500" placeholder="Catatan untuk customer (opsional)" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">{{ old('note') }}</textarea>
                    <button class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg font-medium">Setujui Retur</button>
                </form>
                @endcan

                @can('reject_returns')
                <form method="POST" action="{{ route('admin.returns.reject', $return->id) }}" class="space-y-3">
                    @csrf
                    <h2 class="font-bold">Tolak</h2>
                    <textarea name="note" rows="4" required maxlength="500" placeholder="Alasan penolakan (dilihat customer) *" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"></textarea>
                    @error('note')<p class="text-red-600 text-xs">{{ $message }}</p>@enderror
                    <button class="border border-red-300 text-red-700 hover:bg-red-50 px-5 py-2.5 rounded-lg font-medium">Tolak Retur</button>
                </form>
                @endcan
            </div>
        @elseif($stage === 'approved')
            @can('approve_returns')
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h2 class="font-bold mb-1">Transfer refund</h2>
                <p class="text-sm text-stone-600 mb-4">
                    Transfer <strong>Rp {{ number_format($return->refund_amount, 0, ',', '.') }}</strong> ke
                    <strong>{{ $return->refund_bank }} {{ $return->refund_account_number }}</strong> a/n {{ $return->refund_account_name }}, lalu tandai selesai.
                </p>
                <form method="POST" action="{{ route('admin.returns.refunded', $return->id) }}" onsubmit="return confirm('Dana sudah ditransfer ke customer?')">
                    @csrf
                    <button class="bg-stone-900 hover:bg-stone-800 text-white px-5 py-2.5 rounded-lg font-medium">Tandai Refund Selesai</button>
                </form>
            </div>
            @endcan
        @endif
    </div>

    <div class="space-y-6">
        <div class="bg-white p-6 rounded-lg border border-stone-200 text-sm space-y-2">
            <h2 class="font-bold mb-2">Pesanan</h2>
            <div class="flex justify-between"><span class="text-stone-500">Total</span><span class="font-bold">Rp {{ number_format($order->total, 0, ',', '.') }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Selesai</span><span>{{ $order->completed_at?->format('d M Y') ?? '-' }}</span></div>
            <div class="flex justify-between"><span class="text-stone-500">Diajukan</span><span>{{ $return->created_at->format('d M Y') }}</span></div>
            <div class="pt-2 border-t border-stone-100">{{ $order->shipping_name ?: ($order->customer->name ?? '-') }}</div>
            <ul class="pt-2 border-t border-stone-100 space-y-1">
                @foreach($order->items as $item)<li class="flex justify-between gap-3"><span>{{ $item->product_name }} × {{ $item->quantity }}</span></li>@endforeach
            </ul>
            @can('view_orders')<a href="{{ route('admin.orders.show', $order->id) }}" class="inline-block pt-2 text-orange-600 hover:underline">Buka pesanan →</a>@endcan
        </div>

        <div class="bg-white p-6 rounded-lg border border-stone-200 text-sm space-y-1">
            <h2 class="font-bold mb-2">Rekening refund</h2>
            <div>{{ $return->refund_bank }}</div>
            <div class="font-mono">{{ $return->refund_account_number }}</div>
            <div class="text-stone-600">a/n {{ $return->refund_account_name }}</div>
        </div>

        @if($return->approved_at)
            <div class="bg-white p-6 rounded-lg border border-stone-200 text-sm space-y-1">
                <h2 class="font-bold mb-2">Keputusan</h2>
                <div>{{ $return->status === 'approved' ? 'Disetujui' : 'Ditolak' }} oleh {{ $return->approvedBy->name ?? '-' }}</div>
                <div class="text-stone-500">{{ $return->approved_at->format('d M Y H:i') }}</div>
                @if($return->status === 'approved')
                    <div>Refund Rp {{ number_format($return->refund_amount, 0, ',', '.') }}</div>
                    <div>{{ $return->restocked ? 'Stok dikembalikan' : 'Stok tidak ditambah' }}</div>
                    @if($return->refunded_at)<div class="text-green-700">Refund selesai {{ $return->refunded_at->format('d M Y') }}</div>@endif
                @endif
                @if($return->note)<div class="pt-2 border-t border-stone-100 text-stone-600">{{ $return->note }}</div>@endif
            </div>
        @endif
    </div>
</div>
@endsection
