@props(['order', 'admin' => false])
@php
    $order->loadMissing(['payment', 'shipment']);
    $ship = $order->shipment;
    $statusOrder = ['menunggu_bayar' => 0, 'dikonfirmasi' => 1, 'diproses' => 2, 'dikirim' => 3, 'selesai' => 4];
    $rank = $statusOrder[$order->status] ?? -1;
    $canceled = in_array($order->status, ['batal', 'dibatalkan', 'kedaluwarsa'], true);

    // Urut kronologis, ditampilkan terbaru di atas.
    $steps = [['at' => $order->created_at, 'title' => 'Pesanan dibuat', 'desc' => $order->order_number, 'done' => true]];
    if ($rank >= 1) {
        $steps[] = ['at' => $order->payment?->verified_at ?? $order->payment?->paid_at, 'title' => 'Pembayaran dikonfirmasi', 'desc' => null, 'done' => true];
    }
    if ($rank >= 2) {
        $steps[] = ['at' => null, 'title' => 'Pesanan dikemas', 'desc' => null, 'done' => true];
    }
    if ($rank >= 3 && $ship && $ship->tracking_number) {
        $steps[] = ['at' => $ship->shipped_at, 'title' => 'Dikirim', 'desc' => trim(($ship->courier ?? '') . ' · resi ' . $ship->tracking_number, ' ·'), 'done' => true, 'events' => $ship->tracking_events ?? []];
    }
    if ($rank >= 4) {
        $steps[] = ['at' => $order->completed_at ?? $ship?->delivered_at, 'title' => 'Pesanan selesai', 'desc' => 'Paket sudah diterima.', 'done' => true];
    }
    if ($canceled) {
        $steps[] = ['at' => $order->updated_at, 'title' => 'Pesanan dibatalkan', 'desc' => null, 'done' => true];
    }
    $steps = array_reverse($steps);
@endphp

<div class="mb-6 bg-white rounded-lg border border-stone-200 p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
        <h3 class="font-semibold text-stone-900">Timeline pesanan</h3>
        @if($ship && $ship->tracking_checked_at)
            <span class="text-xs text-stone-500">Diperbarui {{ wib($ship->tracking_checked_at, 'd M, H:i') }} WIB</span>
        @endif
    </div>

    <ol class="relative border-l border-stone-200 ml-2 space-y-5">
        @foreach($steps as $s)
            <li class="ml-5">
                <span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full {{ $loop->first ? 'bg-orange-500 ring-4 ring-orange-100' : 'bg-stone-400' }}"></span>
                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                    <p class="text-sm font-medium text-stone-900">{{ $s['title'] }}</p>
                    @if($s['at'])<p class="text-xs text-stone-500">{{ wib($s['at'], 'd M Y, H:i') }}</p>@endif
                </div>
                @if($s['desc'])<p class="text-xs text-stone-600 mt-0.5">{{ $s['desc'] }}</p>@endif

                @if(! empty($s['events']))
                    <ul class="mt-2 space-y-1.5 border-l border-dashed border-stone-300 pl-3">
                        @foreach($s['events'] as $e)
                            <li class="text-xs text-stone-600"><span class="text-stone-400">{{ $e['at'] }}</span> {{ $e['desc'] }}@if(! empty($e['city'])) <span class="text-stone-400">({{ $e['city'] }})</span>@endif</li>
                        @endforeach
                    </ul>
                @elseif($ship && $s['title'] === 'Dikirim' && $order->status === 'dikirim')
                    <p class="text-xs text-stone-500 mt-1">Perjalanan paket akan muncul di sini begitu kurir memperbarui statusnya.</p>
                @endif
            </li>
        @endforeach
    </ol>

    @if($admin && $ship && $ship->tracking_number)
        @can('manage_shipments')
            <form method="POST" action="{{ route('admin.orders.track', $order->id) }}" class="mt-4">@csrf
                <button class="text-sm border border-stone-300 rounded-lg px-3 py-1.5 hover:bg-stone-50">Cek resi sekarang</button>
            </form>
        @endcan
    @endif
</div>
