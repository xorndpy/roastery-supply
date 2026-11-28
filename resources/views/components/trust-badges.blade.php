@props(['variant' => 'footer'])
@php
    // Hanya memuat jaminan yang benar-benar ada di sistem: garansi per unit, retur online, email rincian pesanan, resi.
    $days = \App\Services\ReturnService::WINDOW_DAYS;
    $footerItems = [
        ['Garansi tercatat per unit', 'Masa garansi otomatis masuk ke akun Anda.'],
        ["Retur {$days} hari", 'Ajukan online bila barang bermasalah.'],
        ['Rincian lewat email', 'Ringkasan harga dan alamat kirim langsung ke email Anda.'],
        ['Pantau pengiriman', 'Nomor resi dan status ada di halaman pesanan.'],
    ];
    $checkoutItems = array_values(array_filter([
        request()->isSecure() ? 'Data Anda dikirim lewat koneksi terenkripsi (HTTPS).' : null,
        "Retur {$days} hari dan garansi tercatat per unit.",
        'Rincian pesanan dikirim ke email Anda.',
    ]));
@endphp
@once
<style>
    .koff-trust ul { list-style: none; margin: 0; padding: 0; }
    .koff-trust svg { flex: none; width: 20px; height: 20px; color: #8f5230; }
    .koff-trust-strip { border-top: 1px solid #e7e5e4; background: #faf7f2; }
    .koff-trust-strip ul { display: grid; gap: 20px; padding: 24px 0; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); }
    .koff-trust-strip li { display: flex; gap: 12px; align-items: flex-start; }
    .koff-trust-strip strong { display: block; font-size: .875rem; color: #1c1917; }
    .koff-trust-strip span { display: block; margin-top: 2px; font-size: .8125rem; line-height: 1.5; color: #57534e; }
    .koff-trust-co ul { margin-top: 16px; padding-top: 14px; border-top: 1px solid #e7e5e4; display: grid; gap: 8px; }
    .koff-trust-co li { display: flex; gap: 10px; align-items: flex-start; font-size: .8125rem; line-height: 1.5; color: #44403c; }
    .koff-trust-co svg { margin-top: 1px; width: 18px; height: 18px; }
</style>
@endonce
@php
    $check = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/></svg>';
@endphp

@if($variant === 'checkout')
    <div class="koff-trust koff-trust-co">
        <ul aria-label="Jaminan belanja">
            @foreach($checkoutItems as $text)
                <li>{!! $check !!}<span>{{ $text }}</span></li>
            @endforeach
        </ul>
    </div>
@else
    <div class="koff-trust koff-trust-strip">
        <div class="container-x">
            <ul aria-label="Jaminan belanja">
                @foreach($footerItems as [$title, $text])
                    <li>{!! $check !!}<div><strong>{{ $title }}</strong><span>{{ $text }}</span></div></li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
