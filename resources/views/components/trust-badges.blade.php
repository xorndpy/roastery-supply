{{-- Poin jaminan singkat di dekat tombol "Buat Pesanan". Hanya memuat hal yang benar-benar ada di sistem. --}}
@php
    $days = \App\Services\ReturnService::WINDOW_DAYS;
    $items = array_values(array_filter([
        request()->isSecure() ? 'Data Anda dikirim lewat koneksi terenkripsi (HTTPS).' : null,
        "Retur {$days} hari dan garansi tercatat per unit.",
        'Rincian pesanan dikirim ke email Anda.',
    ]));
@endphp
@once
<style>
    .koff-trust ul { list-style: none; margin: 16px 0 0; padding: 14px 0 0; border-top: 1px solid #e7e5e4; display: grid; gap: 8px; }
    .koff-trust li { display: flex; gap: 10px; align-items: flex-start; font-size: .8125rem; line-height: 1.5; color: #44403c; }
    .koff-trust svg { flex: none; width: 18px; height: 18px; margin-top: 1px; color: #8f5230; }
</style>
@endonce
<div class="koff-trust">
    <ul aria-label="Jaminan belanja">
        @foreach($items as $text)
            <li><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg><span>{{ $text }}</span></li>
        @endforeach
    </ul>
</div>
