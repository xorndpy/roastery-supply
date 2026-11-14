<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Invoice {{ $order->invoice_number }}</title>
@php
    // Semua teks/logo berasal dari Pengaturan (admin: Sistem > Pengaturan > Umum, Kontak, Media Sosial, Invoice).
    $s = fn (string $key) => \App\Models\Setting::get($key);
    $siteName = $s('site_name');
    $logo = pdf_image_data_uri($s('invoice_logo') ?: $s('site_logo'), 520);
    $host = preg_replace('/^www\./', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST));
    $handle = function (?string $url): ?string {
        if (! $url) return null;
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $h = ltrim(basename(rtrim($path, '/')), '@');
        return $h !== '' ? '@' . $h : null;
    };
    $instagram = $handle($s('social_instagram'));
    $paid = $order->payment && $order->payment->status === 'verified';
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $phone = $order->shipping_phone ?: ($order->customer->phone ?? '');
    $taxRate = (string) $s('tax_enabled') === '1' ? ((float) $s('tax_rate') ?: (float) config('finance.default_tax_rate', 11)) : null;
    $ppnIncluded = $taxRate ? round($order->subtotal - $order->discount) * $taxRate / (100 + $taxRate) : 0;
@endphp
<style>
    /* dompdf: tanpa flex/grid, pakai tabel. Footer tetap di tiap halaman lewat position: fixed di area margin bawah. */
    @page { margin: 34px 40px 78px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1c1917; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    td, th { vertical-align: top; }
    .right { text-align: right; }
    .muted { color: #57534e; }
    .brand { font-size: 28px; font-weight: bold; letter-spacing: 2px; }
    .tagline { font-size: 9.5px; letter-spacing: 1.4px; color: #44403c; margin-top: 6px; }
    .contact { font-size: 9.5px; color: #292524; }
    .title { font-size: 34px; font-weight: bold; letter-spacing: 1px; line-height: 1; }
    .number { font-size: 14px; margin-top: 4px; }
    .rule { border-top: 1px solid #1c1917; margin: 20px 0 16px; }
    .label { font-size: 11px; font-weight: bold; }
    .name { font-size: 14px; font-weight: bold; margin: 2px 0; }
    .status { font-size: 26px; font-weight: bold; letter-spacing: 1px; margin-top: 12px; }
    .status.unpaid { font-size: 15px; }
    .items { margin-top: 18px; border-top: 1.5px solid #1c1917; border-bottom: 1px solid #1c1917; }
    .items th { text-align: left; padding: 9px 4px 8px; font-size: 11.5px; border-bottom: 1px solid #78716c; }
    .items th.right { text-align: right; }
    .items td { padding: 10px 4px; }
    .items tr.sep td { border-bottom: 1px solid #e7e5e4; }
    .sku { font-size: 9.5px; color: #57534e; }
    .totals td { padding: 3px 4px; font-size: 11.5px; }
    .grand td { font-size: 17px; font-weight: bold; padding-top: 9px; border-top: 1px solid #1c1917; }
    .thanks { margin-top: 42px; font-size: 11.5px; }
    .thanks em { color: #44403c; }
    .pay { margin-top: 18px; padding: 9px 11px; border: 1px solid #d6d3d1; font-size: 10px; }
    .footer { position: fixed; left: 0; right: 0; bottom: -56px; border-top: 1px solid #1c1917; padding-top: 11px; font-size: 10.5px; }
    .slogan { font-size: 9.5px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
</style>
</head>
<body>

{{-- Kop: logo + tagline (kiri), kontak (kanan) --}}
<table>
    <tr>
        <td style="width:58%">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $siteName }}" style="height:62px;">
            @else
                <div class="brand">{{ strtoupper($siteName) }}</div>
            @endif
            @if($s('tagline'))<div class="tagline">{{ $s('tagline') }}</div>@endif
        </td>
        <td class="right contact">
            @if($s('contact_address')){!! nl2br(e($s('contact_address'))) !!}<br>@endif
            @if($s('contact_email')){{ $s('contact_email') }}<br>@endif
            @if($s('contact_phone')){{ $s('contact_phone') }}@endif
        </td>
    </tr>
</table>

<div style="margin-top:34px;">
    <div class="title">INVOICE</div>
    <div class="number">{{ $order->invoice_number }}</div>
</div>

<div class="rule"></div>

{{-- Penerima + info pesanan --}}
<table>
    <tr>
        <td style="width:52%">
            <div class="label">Ditagihkan / dikirim ke</div>
            <div class="name">{{ $order->shipping_name ?: ($order->customer->name ?? '-') }}</div>
            @if($phone)<div style="font-weight:bold;">{{ $phone }}</div>@endif
            <div class="muted" style="margin-top:2px;">{{ $order->full_shipping_address ?: ($order->customer->address ?? '-') }}</div>
        </td>
        <td class="right">
            <table style="width:auto; margin-left:auto;">
                <tr><td class="muted" style="padding:1px 14px 1px 0;">No. Pesanan</td><td style="padding:1px 0;">: {{ $order->order_number }}</td></tr>
                <tr><td class="muted" style="padding:1px 14px 1px 0;">Tanggal</td><td style="padding:1px 0;">: {{ wib($order->created_at, 'd M Y') }}</td></tr>
            </table>
            <div class="status {{ $paid ? '' : 'unpaid' }}">{{ $paid ? 'LUNAS' : 'BELUM DIBAYAR' }}</div>
        </td>
    </tr>
</table>

{{-- Rincian --}}
<table class="items">
    <thead>
        <tr>
            <th>Produk</th>
            <th class="right" style="width:22%">Harga</th>
            <th class="right" style="width:9%">Qty</th>
            <th class="right" style="width:22%">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            <tr class="{{ $loop->last ? '' : 'sep' }}">
                <td>
                    {{ $item->product_name }}
                    @if($item->product_sku)<br><span class="sku">SKU: {{ $item->product_sku }}</span>@endif
                </td>
                <td class="right">{{ $rp($item->price) }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ $rp($item->subtotal) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top:16px;">
    <tr>
        <td style="width:46%"></td>
        <td>
            <table class="totals">
                <tr><td>Subtotal</td><td class="right">{{ $rp($order->subtotal) }}</td></tr>
                @if($order->discount > 0)
                    <tr><td>Diskon{{ $order->voucher_code ? ' (' . $order->voucher_code . ')' : '' }}</td><td class="right">- {{ $rp($order->discount) }}</td></tr>
                @endif
                <tr><td>Ongkir{{ $order->shipping_method ? ' (' . $order->shipping_method . ($order->shipping_etd ? ', ' . $order->shipping_etd : '') . ')' : '' }}</td><td class="right">{{ $rp($order->shipping_cost) }}</td></tr>
                <tr class="grand"><td>Total</td><td class="right">{{ $rp($order->total) }}</td></tr>
                @if($taxRate)
                    <tr><td class="muted" style="font-size:9.5px;">Sudah termasuk PPN {{ rtrim(rtrim(number_format($taxRate, 2, ',', ''), '0'), ',') }}%</td><td class="right muted" style="font-size:9.5px;">{{ $rp($ppnIncluded) }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

@if($order->note)
    <p class="muted" style="margin-top:14px;">Catatan: {{ $order->note }}</p>
@endif

@if(! $paid && $s('bank_name') && $s('bank_account_number'))
    <div class="pay">
        <strong>Pembayaran:</strong> {{ $s('bank_name') }} {{ $s('bank_account_number') }}@if($s('bank_account_name')) a.n. {{ $s('bank_account_name') }}@endif
    </div>
@endif

<div class="thanks">
    Terima kasih telah berbelanja di <strong>{{ $siteName }}</strong>.
    @if($s('invoice_thanks'))<br><em>{{ $s('invoice_thanks') }}</em>@endif
</div>

{{-- Footer di setiap halaman --}}
<div class="footer">
    <table>
        <tr>
            <td>
                @if($host){{ 'www.' . $host }}@endif
                @if($host && $instagram) &nbsp;&nbsp;|&nbsp;&nbsp; @endif
                @if($instagram){{ $instagram }}@endif
            </td>
            <td class="right slogan">{{ $s('invoice_slogan') }}</td>
        </tr>
    </table>
</div>

</body>
</html>
