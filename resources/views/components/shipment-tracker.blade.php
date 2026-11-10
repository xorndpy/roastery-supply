@props(['order', 'shipment' => null, 'compact' => false])
@php
    $shipment = $shipment ?? $order->shipment;

    // Tahap: 0 menunggu bayar, 1 dibayar, 2 dikemas, 3 dalam perjalanan, 4 tiba.
    $stage = match (true) {
        ($shipment && $shipment->status === 'delivered') || $order->status === 'selesai' => 4,
        $order->status === 'dikirim' || ($shipment && $shipment->status === 'shipped') => 3,
        $order->status === 'diproses' => 2,
        $order->status === 'dikonfirmasi' => 1,
        default => 0,
    };
    $cancelled = $order->status === 'batal';
    $moving = $stage === 3;
    $fmt = fn ($d) => $d?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M, H:i');

    $steps = [
        ['label' => 'Dibayar', 'time' => null],
        ['label' => 'Dikemas', 'time' => null],
        ['label' => 'Dalam perjalanan', 'time' => $fmt($shipment?->shipped_at)],
        ['label' => 'Tiba', 'time' => $fmt($shipment?->delivered_at ?? ($stage === 4 ? $order->completed_at : null))],
    ];
    // Truk berhenti di titik tahap saat ini (posisi nyata di jalan tidak dilacak oleh sistem).
    $pos = [0 => 0, 1 => 0, 2 => 33.3, 3 => 66.6, 4 => 100][$stage];
    $caption = [0 => 'Menunggu pembayaran', 1 => 'Pembayaran diterima, menunggu dikemas', 2 => 'Sedang dikemas', 3 => 'Paket dalam perjalanan', 4 => 'Paket sudah tiba'][$stage];
    $uid = 'trk' . substr(md5(($order->id ?? '') . ($shipment->id ?? '') . ($compact ? 'c' : 'f')), 0, 6);
@endphp

@once
<style>
    .trk { --accent:#a8643a; --accent-d:#8f5230; --rule:#e4e4e7; --muted:#71717a; color:#18181b; }
    .trk-road { position: relative; height: 56px; margin: 0 14px; }
    .trk-line { position: absolute; left: 0; right: 0; bottom: 8px; height: 4px; border-radius: 999px; background: #ececef; }
    .trk-fill { position: absolute; left: 0; bottom: 8px; height: 4px; border-radius: 999px; background: linear-gradient(90deg, #c48655, var(--accent)); width: var(--to); animation: trk-fill 1.1s cubic-bezier(.22,.8,.3,1) both; }
    .trk-dots { position: absolute; left: 0; right: 0; bottom: 4px; display: flex; justify-content: space-between; }
    .trk-dot { position: relative; width: 12px; height: 12px; margin: 0 -6px; border-radius: 999px; background: #fff; border: 2px solid #d4d4d8; transition: background-color .3s, border-color .3s; }
    .trk-dot.is-done { background: var(--accent); border-color: var(--accent); }
    .trk-dot.is-now { box-shadow: 0 0 0 5px rgb(168 100 58 / .18); }
    .trk-truck { position: absolute; bottom: 14px; left: var(--to); width: 46px; margin-left: -23px; animation: trk-drive 1.1s cubic-bezier(.22,.8,.3,1) both; }
    .trk-truck svg { display: block; width: 46px; height: 30px; }
    .trk.is-moving .trk-truck svg { animation: trk-bob .5s ease-in-out infinite; }
    .trk-wheel { transform-box: fill-box; transform-origin: center; }
    .trk.is-moving .trk-wheel { animation: trk-spin .6s linear infinite; }
    .trk-wind { position: absolute; bottom: 20px; left: var(--to); margin-left: -46px; width: 18px; opacity: 0; }
    .trk.is-moving .trk-wind { opacity: 1; animation: trk-wind .9s ease-out infinite; }
    .trk-steps { display: grid; grid-template-columns: repeat(4, 1fr); margin-top: .35rem; }
    .trk-step { font-size: 12.5px; color: var(--muted); text-align: center; line-height: 1.35; }
    .trk-step:first-child { text-align: left; } .trk-step:last-child { text-align: right; }
    .trk-step b { display: block; font-weight: 500; } .trk-step small { display: block; font-size: 11.5px; }
    .trk-step.is-done { color: #18181b; } .trk-step.is-done b { font-weight: 600; }
    .trk-cap { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .75rem; margin-bottom: .35rem; font-size: 13.5px; font-weight: 600; }
    .trk-cap span { display: inline-flex; align-items: center; gap: .4rem; padding: .15rem .6rem; border-radius: 999px; background: #f6ede6; color: var(--accent-d); font-size: 12.5px; }
    .trk-cap em { font-style: normal; font-weight: 400; font-size: 12.5px; color: var(--muted); }
    .trk-mini { display: flex; align-items: center; gap: .6rem; min-width: 150px; }
    .trk-mini .trk-road { flex: 1; height: 30px; margin: 0 6px; }
    .trk-mini .trk-truck { width: 26px; margin-left: -13px; bottom: 8px; } .trk-mini .trk-truck svg { width: 26px; height: 17px; }
    .trk-mini .trk-line { bottom: 4px; height: 3px; } .trk-mini .trk-fill { bottom: 4px; height: 3px; } .trk-mini .trk-dots { display: none; }
    .trk-mini .trk-wind { display: none; }
    .trk-mini small { font-size: 12px; color: var(--muted); white-space: nowrap; }
    @keyframes trk-drive { from { left: 0; } to { left: var(--to); } }
    @keyframes trk-fill { from { width: 0; } to { width: var(--to); } }
    @keyframes trk-bob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-1.5px); } }
    @keyframes trk-spin { to { transform: rotate(360deg); } }
    @keyframes trk-wind { 0% { transform: translateX(8px); opacity: 0; } 30% { opacity: .7; } 100% { transform: translateX(-10px); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { .trk-fill, .trk-truck, .trk.is-moving .trk-truck svg, .trk.is-moving .trk-wheel, .trk.is-moving .trk-wind { animation: none; } }
</style>
@endonce

@unless($cancelled)
@php
    $truckSvg = '<svg viewBox="0 0 46 30" fill="none" aria-hidden="true"><rect x="1" y="4" width="26" height="18" rx="3" fill="#a8643a"/><path d="M27 10h8.5l7 7v5H27V10z" fill="#8f5230"/><path d="M30 12.5h5.2l4 4.5H30v-4.5z" fill="#f4e3d3"/><rect x="5" y="8" width="14" height="2" rx="1" fill="#fff" fill-opacity=".45"/><rect x="5" y="12" width="9" height="2" rx="1" fill="#fff" fill-opacity=".3"/><g class="trk-wheel"><circle cx="11" cy="24" r="4" fill="#24150e"/><circle cx="11" cy="24" r="1.5" fill="#d4d4d8"/></g><g class="trk-wheel"><circle cx="35" cy="24" r="4" fill="#24150e"/><circle cx="35" cy="24" r="1.5" fill="#d4d4d8"/></g></svg>';
@endphp
@if($compact)
    <div class="trk trk-mini {{ $moving ? 'is-moving' : '' }}" style="--to: {{ $pos }}%" role="img" aria-label="{{ $caption }}">
        <div class="trk-road">
            <div class="trk-line"></div><div class="trk-fill"></div>
            <div class="trk-truck">{!! $truckSvg !!}</div>
        </div>
        <small>{{ $stage === 4 ? 'Tiba' : ($stage === 3 ? 'Dikirim' : $steps[max(0, $stage - 1)]['label']) }}</small>
    </div>
@else
    <div class="trk {{ $moving ? 'is-moving' : '' }}" style="--to: {{ $pos }}%" id="{{ $uid }}" role="group" aria-label="Pelacakan pengiriman: {{ $caption }}">
        <div class="trk-cap"><span>{{ $caption }}</span>@if($order->shipping_etd && $stage < 4)<em>Estimasi {{ $order->shipping_etd }}</em>@endif</div>
        <div class="trk-road">
            <div class="trk-line"></div>
            <div class="trk-fill"></div>
            <div class="trk-dots">
                @foreach($steps as $i => $st)
                    <i class="trk-dot {{ $stage >= $i + 1 ? 'is-done' : '' }} {{ $stage === $i + 1 ? 'is-now' : '' }}"></i>
                @endforeach
            </div>
            <svg class="trk-wind" viewBox="0 0 18 14" fill="none" stroke="#a8643a" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M1 3h12M4 7h13M1 11h9"/></svg>
            <div class="trk-truck">{!! $truckSvg !!}</div>
        </div>
        <div class="trk-steps">
            @foreach($steps as $i => $st)
                <div class="trk-step {{ $stage >= $i + 1 ? 'is-done' : '' }}"><b>{{ $st['label'] }}</b>@if($st['time'] && $stage >= $i + 1)<small>{{ $st['time'] }} WIB</small>@endif</div>
            @endforeach
        </div>
    </div>
@endif
@endunless
