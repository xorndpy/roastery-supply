@extends('layouts.admin')

@section('title', 'Detail Pesanan ' . $order->order_number)

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.orders.index') }}" class="text-sm text-stone-500 hover:text-stone-700">
        Kembali ke Daftar Pesanan
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left Column --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Order Info --}}
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-xl font-bold">{{ $order->order_number }}</h2>
                    <div class="text-sm text-stone-500 mt-1">{{ wib($order->created_at, 'd M Y, H:i', true) }}</div>
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
                    <div class="font-medium">{{ $order->shipping_name ?: ($order->customer->name ?? 'Guest') }}</div>
                </div>
                <div>
                    <div class="text-xs text-stone-500">Telepon</div>
                    <div class="font-medium">{{ $order->shipping_phone ?: ($order->customer->phone ?? '-') }}</div>
                </div>
                <div class="col-span-2">
                    <div class="text-xs text-stone-500">Alamat</div>
                    <div class="font-medium">{{ $order->full_shipping_address ?: ($order->customer->address ?? '-') }}</div>
                </div>
            </div>

            @if($order->note)
                <div class="mt-4 pt-4 border-t border-stone-200">
                    <div class="text-xs text-stone-500">Catatan</div>
                    <div class="text-sm">{{ $order->note }}</div>
                </div>
            @endif
        </div>

        {{-- Posisi pesanan --}}
        <div class="bg-white p-6 rounded-lg border border-stone-200">
            <x-order-stepper :order="$order" />
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
                                @can('view_finance')Rp {{ number_format($item->price, 0, ',', '.') }} × @endcan{{ $item->quantity }}@cannot('view_finance') pcs @endcannot
                            </div>
                        </div>
@can('view_finance')
                        <div class="font-medium">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</div>
@endcan
                    </div>
                @endforeach
            </div>

            @can('view_finance')
            <div class="border-t border-stone-200 mt-4 pt-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-stone-600">Subtotal</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($order->discount > 0)
                    <div class="flex justify-between text-sm text-green-600">
                        <span>Diskon{{ $order->voucher_code ? ' (' . $order->voucher_code . ')' : '' }}</span>
                        <span>- Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm">
                    <span class="text-stone-600">Ongkir{{ $order->shipping_method ? ' (' . $order->shipping_method . ($order->shipping_etd ? ', ' . $order->shipping_etd : '') . ')' : '' }}</span>
                    <span>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between font-bold text-lg pt-2 border-t">
                    <span>Total</span>
                    <span class="text-copper-500">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>
            @endcan
        </div>

        {{-- Payment Proof --}}
        @if($order->payment && auth()->user()->can('view_finance'))
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
                            <img src="{{ route('admin.payments.proof', $order->payment->id) }}"
                                 alt="Bukti Pembayaran"
                                 class="w-full rounded border border-stone-200">
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <x-order-timeline :order="$order" :admin="true" />

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

        {{-- Aksi: satu tombol utama sesuai status; sisanya di menu ⋯ --}}
        @php
            $st = $order->status;
            $btnPrimary = 'w-full rounded-lg bg-stone-900 py-3 font-medium text-white transition hover:bg-stone-800';
            $btnDisabled = 'w-full cursor-not-allowed rounded-lg bg-stone-200 py-3 font-medium text-stone-500';
            $hasResi = $order->shipment && $order->shipment->status === 'pending' && $order->shipment->tracking_number;
            $canCancel = in_array($st, ['menunggu_bayar', 'dikonfirmasi', 'diproses']) && auth()->user()->can('cancel_orders');
            $canNote = in_array($st, ['dikonfirmasi', 'diproses', 'dikirim', 'selesai']);
            $canInvoice = auth()->user()->can('download_invoices');
        @endphp
        <div class="bg-white p-6 rounded-lg border border-stone-200 sticky top-24">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="font-bold">Aksi</h3>
                @if($canCancel || $canNote || $canInvoice)
                    <div x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false" class="relative">
                        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Aksi lainnya"
                                class="flex h-8 w-8 items-center justify-center rounded-md text-stone-600 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-stone-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
                        </button>
                        <div x-show="open" x-cloak role="menu" class="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-lg border border-stone-200 bg-white py-1 shadow-lg">
                            @if($canNote)<a href="{{ route('admin.orders.delivery-note', $order->id) }}" target="_blank" role="menuitem" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Cetak surat jalan</a>@endif
                            @if($canInvoice)<a href="{{ route('admin.orders.invoice', $order->id) }}" target="_blank" role="menuitem" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Cetak invoice</a>@endif
                            @if($canCancel)
                                <form method="POST" action="{{ route('admin.orders.cancel', $order->id) }}" class="border-t border-stone-100">
                                    @csrf
                                    <button type="submit" role="menuitem"
                                            data-confirm-title="Batalkan pesanan {{ $order->order_number }}?" data-confirm-text="Status berubah menjadi dibatalkan. Jika stok sudah terpotong, stok dikembalikan. Aksi ini tidak bisa diurungkan." data-confirm-ok="Ya, batalkan pesanan" data-confirm-cancel="Kembali" data-confirm-tone="danger"
                                            class="w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Batalkan pesanan</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if($st === 'menunggu_bayar')
                @if($order->payment && $order->payment->status === 'pending')
                    @can('verify_payments')
                        <form method="POST" action="{{ route('admin.orders.confirm-payment', $order->id) }}">@csrf
                            <button type="submit" class="{{ $btnPrimary }}">Konfirmasi pembayaran</button>
                        </form>
                    @else
                        <button type="button" disabled class="{{ $btnDisabled }}">Konfirmasi pembayaran</button>
                        <p class="mt-2 text-xs text-stone-500">Hanya petugas verifikasi pembayaran yang bisa mengonfirmasi.</p>
                    @endcan
                @else
                    <button type="button" disabled class="{{ $btnDisabled }}">Konfirmasi pembayaran</button>
                    <p class="mt-2 text-xs text-stone-500">Belum ada bukti pembayaran dari pelanggan.</p>
                @endif

            @elseif($st === 'dikonfirmasi')
                @can('process_orders')
                    <form method="POST" action="{{ route('admin.orders.process', $order->id) }}">@csrf
                        <button type="submit" class="{{ $btnPrimary }}">Mulai proses</button>
                    </form>
                    <p class="mt-2 text-xs text-stone-500">Setelah dikemas, isi kurir &amp; resi, lalu kirim.</p>
                @else
                    <button type="button" disabled class="{{ $btnDisabled }}">Mulai proses</button>
                    <p class="mt-2 text-xs text-stone-500">Menunggu gudang memulai proses pengemasan.</p>
                @endcan

            @elseif($st === 'diproses')
                @can('manage_shipments')
                    @include('admin.orders._ship-form', ['order' => $order])
                @endcan
                @can('ship_orders')
                    @if($hasResi)
                        <form method="POST" action="{{ route('admin.orders.ship', $order->id) }}">@csrf
                            <button type="submit" class="{{ $btnPrimary }}">Kirim pesanan</button>
                        </form>
                    @else
                        <button type="button" disabled class="{{ $btnDisabled }}">Kirim pesanan</button>
                        <p class="mt-2 text-xs text-stone-500">Isi kurir &amp; resi dulu. Tombol ini aktif setelah resi tersimpan.</p>
                    @endif
                @else
                    <p class="text-xs text-stone-500">{{ $hasResi ? 'Resi tersimpan. Menunggu admin menekan Kirim.' : 'Isi kurir & resi, lalu admin yang menekan Kirim.' }}</p>
                @endcan

            @elseif($st === 'dikirim')
                @can('complete_orders')
                    <form method="POST" action="{{ route('admin.orders.complete', $order->id) }}">@csrf
                        <button type="submit" class="{{ $btnPrimary }}">Tandai selesai</button>
                    </form>
                @else
                    <button type="button" disabled class="{{ $btnDisabled }}">Tandai selesai</button>
                    <p class="mt-2 text-xs text-stone-500">Hanya admin yang bisa menandai selesai.</p>
                @endcan

            @else
                <p class="text-sm text-stone-500">Tidak ada aksi untuk pesanan ini.</p>
            @endif
        </div>
    </div>
</div>

@endsection