@extends('layouts.app')

@section('title', 'Detail Pesanan ' . $order->order_number)

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <nav class="text-sm text-stone-500 mb-6">
        <a href="{{ route('home') }}" class="hover:text-orange-500">Beranda</a>
        <span class="mx-2">/</span>
        <a href="{{ route('customer.orders.index') }}" class="hover:text-orange-500">Pesanan Saya</a>
        <span class="mx-2">/</span>
        <span class="text-stone-900">{{ $order->order_number }}</span>
    </nav>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
        <div class="flex flex-wrap justify-between items-start gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ $order->order_number }}</h1>
                <div class="text-sm text-stone-500 mt-1">
                    {{ $order->created_at->format('d M Y, H:i') }}
                </div>
            </div>
            <div class="text-right">
                @php
                    $statusColors = [
                        'menunggu_bayar' => 'bg-yellow-100 text-yellow-800',
                        'dikonfirmasi' => 'bg-blue-100 text-blue-800',
                        'diproses' => 'bg-purple-100 text-purple-800',
                        'dikirim' => 'bg-indigo-100 text-indigo-800',
                        'selesai' => 'bg-green-100 text-green-800',
                        'batal' => 'bg-red-100 text-red-800',
                    ];
                    $statusLabels = [
                        'menunggu_bayar' => 'Menunggu Bayar',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'diproses' => 'Diproses',
                        'dikirim' => 'Dikirim',
                        'selesai' => 'Selesai',
                        'batal' => 'Batal',
                    ];
                @endphp
                <span class="text-sm px-4 py-2 rounded-full font-medium {{ $statusColors[$order->status] ?? 'bg-stone-100' }}">
                    {{ $statusLabels[$order->status] ?? $order->status }}
                </span>
            </div>
        </div>
    </div>

    {{-- Detail Item --}}
    <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
        <h2 class="font-bold mb-4">Item Pesanan</h2>
        <div class="space-y-3">
            @foreach($order->items as $item)
                <div class="flex justify-between text-sm">
                    <div>
                        <div class="font-medium">{{ $item->product_name }}</div>
                        <div class="text-stone-500">Rp {{ number_format($item->price, 0, ',', '.') }} × {{ $item->quantity }}</div>
                    </div>
                    <div class="font-medium">
                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-t border-stone-200 mt-4 pt-4 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-stone-600">Subtotal</span>
                <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($order->discount > 0)
                <div class="flex justify-between text-green-600">
                    <span>Diskon</span>
                    <span>- Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between">
                <span class="text-stone-600">Ongkir</span>
                <span>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-bold text-lg pt-2 border-t">
                <span>Total</span>
                <span class="text-orange-500">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Upload Bukti Pembayaran --}}
    @if($order->status === 'menunggu_bayar')
        <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
            <h2 class="font-bold mb-4">Upload Bukti Pembayaran</h2>

            @if($order->payment)
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg mb-4">
                    <div class="font-medium text-yellow-800">Bukti pembayaran sudah diunggah</div>
                    <div class="text-sm text-yellow-700 mt-1">Menunggu verifikasi admin.</div>
                    @if($order->payment->proof_image)
                        <img src="{{ asset('storage/' . $order->payment->proof_image) }}"
                             alt="Bukti" class="mt-3 max-w-xs rounded border">
                    @endif
                </div>
            @else
                <div class="bg-orange-50 border border-orange-200 p-4 rounded-lg mb-6">
                    <div class="font-medium text-orange-900">Instruksi Pembayaran</div>
                    <p class="text-sm text-orange-700 mt-1">
                        Transfer ke rekening berikut, lalu upload bukti transfer:
                    </p>
                    <div class="mt-2 font-mono text-sm bg-white p-3 rounded border border-orange-200">
                        <div>Bank BCA</div>
                        <div>No. Rek: <strong>1234567890</strong></div>
                        <div>a/n: Roastery Supply</div>
                    </div>
                    <div class="mt-3 text-sm text-orange-700">
                        Jumlah: <strong>Rp {{ number_format($order->total, 0, ',', '.') }}</strong>
                    </div>
                </div>

                <form method="POST" action="{{ route('customer.orders.pay', $order->order_number) }}"
                      enctype="multipart/form-data" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Metode Pembayaran *</label>
                            <select name="method" required
                                    class="w-full border border-stone-300 rounded-lg px-3 py-2">
                                <option value="transfer_bca">Transfer BCA</option>
                                <option value="transfer_mandiri">Transfer Mandiri</option>
                                <option value="transfer_bni">Transfer BNI</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Jumlah Transfer *</label>
                            <input type="number" name="amount" value="{{ $order->total }}" required
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Bukti Transfer *</label>
                        <input type="file" name="proof_image" accept="image/*" required
                               class="w-full border border-stone-300 rounded-lg px-3 py-2">
                        @error('proof_image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Catatan (opsional)</label>
                        <textarea name="note" rows="2"
                                  class="w-full border border-stone-300 rounded-lg px-3 py-2"></textarea>
                    </div>

                    <button type="submit"
                            class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
                        Upload Bukti Pembayaran
                    </button>
                </form>
            @endif
        </div>
    @endif

    {{-- Info Pengiriman --}}
    @if($order->shipment && $order->shipment->tracking_number)
        <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
            <h2 class="font-bold mb-4">Info Pengiriman</h2>
            <div class="text-sm space-y-2">
                <div><span class="text-stone-500">Kurir:</span> {{ $order->shipment->courier ?? '-' }}</div>
                <div><span class="text-stone-500">No. Resi:</span> <strong>{{ $order->shipment->tracking_number }}</strong></div>
            </div>
        </div>
    @endif

    <div class="text-center">
        <a href="{{ route('customer.orders.index') }}" 
           class="text-sm text-stone-500 hover:text-stone-700">
            ← Kembali ke Daftar Pesanan
        </a>
    </div>
</div>
@endsection