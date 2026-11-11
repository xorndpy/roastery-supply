@props(['order'])
@php
    // Posisi pesanan di alur: 0 dipesan, 1 dibayar, 2 dikemas, 3 dikirim, 4 selesai.
    $at = ['menunggu_bayar' => 0, 'dikonfirmasi' => 1, 'diproses' => 2, 'dikirim' => 3, 'selesai' => 4][$order->status] ?? null;
    $steps = [
        ['Dipesan', $order->created_at],
        ['Dibayar', null],
        ['Dikemas', null],
        ['Dikirim', $order->shipment?->shipped_at],
        ['Selesai', $order->completed_at],
    ];
@endphp
<style>
    .ostep { display: flex; list-style: none; margin: 0; padding: 0; gap: .25rem; }
    .ostep li { flex: 1 1 0; min-width: 0; text-align: center; position: relative; }
    .ostep li::before { content: ''; position: absolute; top: 15px; left: -50%; width: 100%; height: 2px; background: #e7e5e4; z-index: 0; }
    .ostep li:first-child::before { display: none; }
    .ostep li.is-done::before, .ostep li.is-now::before { background: #a8643a; }
    .ostep .dot { position: relative; z-index: 1; display: grid; place-items: center; width: 32px; height: 32px; margin: 0 auto; border-radius: 999px; border: 2px solid #d6d3d1; background: #fff; color: #78716c; font-size: 13px; font-weight: 600; }
    .ostep li.is-done .dot { background: #a8643a; border-color: #a8643a; color: #fff; }
    .ostep li.is-now .dot { border-color: #a8643a; color: #8f5230; box-shadow: 0 0 0 4px rgb(168 100 58 / .15); }
    .ostep .lbl { display: block; margin-top: .4rem; font-size: 12.5px; font-weight: 500; color: #57534e; }
    .ostep li.is-now .lbl, .ostep li.is-done .lbl { color: #1c1917; }
    .ostep .when { display: block; font-size: 11px; color: #78716c; }
    .ostep-cancel { padding: .75rem 1rem; border: 1px solid #fecaca; border-radius: 10px; background: #fef2f2; color: #991b1b; font-size: 13.5px; }
</style>
@if($at === null)
    <div class="ostep-cancel" role="status">Pesanan ini dibatalkan.</div>
@else
    <ol class="ostep" aria-label="Posisi pesanan">
        @foreach($steps as $i => [$label, $when])
            @php $state = $i < $at || $at === 4 ? 'is-done' : ($i === $at ? 'is-now' : ''); @endphp
            <li class="{{ $state }}" @if($state === 'is-now') aria-current="step" @endif>
                <span class="dot" aria-hidden="true">@if($state === 'is-done')&#10003;@else{{ $i + 1 }}@endif</span>
                <span class="lbl">{{ $label }}<span class="sr-only">{{ $state === 'is-done' ? ' (selesai)' : ($state === 'is-now' ? ' (tahap saat ini)' : ' (belum)') }}</span></span>
                @if($when && $state !== '')<span class="when">{{ wib($when, 'j M, H:i') }}</span>@endif
            </li>
        @endforeach
    </ol>
@endif
