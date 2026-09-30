@extends('layouts.admin')

@section('title', 'Detail Pesanan ' . $order->order_number)

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.orders.index') }}" class="text-sm text-stone-500 hover:text-stone-700">
        ← Kembali ke Daftar Pesanan
    </a>
</div>

@if(session('success'))
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
        {{ session('error') }}
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left Column --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Order Info --}}
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-xl font-bold">{{ $order->order_number }}</h2>
                    <div class="text-sm text-stone-500 mt-1">{{ $order->created_at->format('d M Y, H:i') }}</div>
                </div>
                <span class="text-sm px-4 py-2 rounded-full font-medium
                    @if($order->status === 'menunggu_bayar') bg-yellow-100 text-yellow-800
                    @elseif($order->status === 'dikonfirmasi') bg-blue-100 text-blue-800
                    @elseif($order->status === 'diproses') bg-purple-100 text-purple-800
                    @elseif($order->status === 'dikirim') bg-indigo-100 text-indigo-800
                    @elseif($order->status === 'selesai') bg-green-100 text-green-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-stone-200">
                <div>
                    <div class="text-xs text-stone-500">Nama</div>
                    <div class="font-medium">{{ $order->customer->name ?? 'Guest' }}</div>
                </div>
                <div>
                    <div class="text-xs text-stone-500">Telepon</div>
                    <div class="font-medium">{{ $order->customer->phone ?? '-' }}</div>
                </div>
                <div class="col-span-2">
                    <div class="text-xs text-stone-500">Alamat</div>
                    <div class="font-medium">{{ $order->customer->address ?? '-' }}</div>
                </div>
            </div>

            @if($order->note)
                <div class="mt-4 pt-4 border-t border-stone-200">
                    <div class="text-xs text-stone-500">Catatan</div>
                    <div class="text-sm">{{ $order->note }}</div>
                </div>
            @endif
        </div>

        {{-- Items --}}
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <h3 class="font-bold mb-4">Item Pesanan</h3>
            <div class="space-y-3">
                @foreach($order->items as $item)
                    <div class="flex justify-between">
                        <div>
                            <div class="font-medium">{{ $item->product_name }}</div>
                            <div class="text-sm text-stone-500">
                                Rp {{ number_format($item->price, 0, ',', '.') }} × {{ $item->quantity }}
                            </div>
                        </div>
                        <div class="font-medium">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-stone-200 mt-4 pt-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-stone-600">Subtotal</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($order->discount > 0)
                    <div class="flex justify-between text-sm text-green-600">
                        <span>Diskon</span>
                        <span>- Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm">
                    <span class="text-stone-600">Ongkir</span>
                    <span>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between font-bold text-lg pt-2 border-t">
                    <span>Total</span>
                    <span class="text-orange-500">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Payment Proof --}}
        @if($order->payment)
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h3 class="font-bold mb-4">Bukti Pembayaran</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-stone-500">Metode</div>
                        <div class="font-medium">{{ $order->payment->method }}</div>
                        <div class="text-xs text-stone-500 mt-2">Jumlah</div>
                        <div class="font-medium">Rp {{ number_format($order->payment->amount, 0, ',', '.') }}</div>
                        <div class="text-xs text-stone-500 mt-2">Status</div>
                        <div class="font-medium">{{ ucfirst($order->payment->status) }}</div>
                    </div>
                    <div>
                        @if($order->payment->proof_image)
                            <img src="{{ asset('storage/' . $order->payment->proof_image) }}"
                                 alt="Bukti Pembayaran"
                                 class="w-full rounded border border-stone-200">
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Shipment Info --}}
        @if($order->shipment && $order->shipment->tracking_number)
            <div class="bg-white p-6 rounded-lg border border-stone-200">
                <h3 class="font-bold mb-4">Info Pengiriman</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-stone-500">Kurir</div>
                        <div class="font-medium">{{ $order->shipment->courier ?? '-' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-stone-500">No. Resi</div>
                        <div class="font-medium">{{ $order->shipment->tracking_number }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Right Column - Actions --}}
    <div class="lg:col-span-1 space-y-4">

        {{-- Action Buttons --}}
        <div class="bg-white p-6 rounded-lg border border-stone-200 sticky top-24">
            <h3 class="font-bold mb-4">Aksi</h3>

            {{-- Konfirmasi Pembayaran --}}
            @if($order->status === 'menunggu_bayar' && $order->payment && $order->payment->status === 'pending')
                <form method="POST" action="{{ route('admin.orders.confirm-payment', $order->id) }}" class="mb-3">
                    @csrf
                    <button type="submit"
                            class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg font-medium transition">
                        ✓ Konfirmasi Pembayaran
                    </button>
                </form>
            @endif

            {{-- Proses --}}
            @if($order->status === 'dikonfirmasi')
                <form method="POST" action="{{ route('admin.orders.process', $order->id) }}" class="mb-3">
                    @csrf
                    <button type="submit"
                            class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-lg font-medium transition">
                        Mulai Proses
                    </button>
                </form>
            @endif

            {{-- Ship --}}
            @if(in_array($order->status, ['dikonfirmasi', 'diproses']))
                <form method="POST" action="{{ route('admin.orders.ship', $order->id) }}" class="mb-3 space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium mb-1">Kurir *</label>
                        <input type="text" name="courier" placeholder="JNE, J&T, SiCepat..." required
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">No. Resi *</label>
                        <input type="text" name="tracking_number" required
                               class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <button type="submit"
                            class="w-full bg-indigo-500 hover:bg-indigo-600 text-white py-3 rounded-lg font-medium transition">
                        Kirim Pesanan
                    </button>
                </form>
            @endif

            {{-- Complete --}}
            @if($order->status === 'dikirim')
                <form method="POST" action="{{ route('admin.orders.complete', $order->id) }}" class="mb-3">
                    @csrf
                    <button type="submit"
                            class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg font-medium transition">
                        ✓ Tandai Selesai
                    </button>
                </form>
            @endif

            {{-- Cancel --}}
            @if(in_array($order->status, ['menunggu_bayar', 'dikonfirmasi', 'diproses']))
                <form method="POST" action="{{ route('admin.orders.cancel', $order->id) }}">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Yakin batalkan pesanan ini?')"
                            class="w-full border border-red-300 text-red-600 hover:bg-red-50 py-3 rounded-lg font-medium transition">
                        Batalkan Pesanan
                    </button>
                </form>
            @endif

            {{-- Invoice --}}
            <a href="{{ route('admin.orders.invoice', $order->id) }}"
               target="_blank"
               class="block mt-3 text-center border border-stone-300 hover:bg-stone-50 py-2 rounded-lg text-sm">
                🖨️ Cetak Invoice
            </a>
        </div>
    </div>
</div>

@endsection