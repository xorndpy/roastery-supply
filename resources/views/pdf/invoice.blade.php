<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Invoice {{ $order->invoice_number }}</title>
<style>
    /* dompdf: tanpa flex/grid, pakai tabel */
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c1917; }
    h1 { font-size: 20px; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #78716c; }
    .right { text-align: right; }
    .items th { background: #f5f5f4; text-align: left; padding: 7px 8px; border-bottom: 1px solid #d6d3d1; }
    .items td { padding: 7px 8px; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
    .totals td { padding: 4px 8px; }
    .grand td { font-size: 13px; font-weight: bold; border-top: 1px solid #1c1917; padding-top: 8px; }
    .status { display: inline-block; padding: 3px 8px; border: 1px solid #1c1917; font-weight: bold; font-size: 10px; }
</style>
</head>
<body>
@php
    $paid = $order->payment && $order->payment->status === 'verified';
@endphp

<table>
    <tr>
        <td>
            <h1>INVOICE</h1>
            <div class="muted">{{ $order->invoice_number }}</div>
        </td>
        <td class="right">
            <strong>{{ \App\Models\Setting::get('site_name') }}</strong><br>
            <span class="muted">{{ \App\Models\Setting::get('tagline') }}</span>
            @if(\App\Models\Setting::get('contact_address'))<br><span class="muted">{{ \App\Models\Setting::get('contact_address') }}</span>@endif
            @if(\App\Models\Setting::get('contact_email'))<br><span class="muted">{{ \App\Models\Setting::get('contact_email') }}</span>@endif
            @if(\App\Models\Setting::get('contact_phone'))<br><span class="muted">{{ \App\Models\Setting::get('contact_phone') }}</span>@endif
        </td>
    </tr>
</table>

<br>

<table>
    <tr>
        <td style="width:50%; vertical-align: top;">
            <div class="muted">Ditagihkan / dikirim ke</div>
            <strong>{{ $order->shipping_name ?: ($order->customer->name ?? '-') }}</strong><br>
            {{ $order->shipping_phone ?: ($order->customer->phone ?? '') }}<br>
            {{ $order->full_shipping_address ?: ($order->customer->address ?? '-') }}
        </td>
        <td class="right" style="vertical-align: top;">
            <div><span class="muted">No. Pesanan:</span> {{ $order->order_number }}</div>
            <div><span class="muted">Tanggal:</span> {{ $order->created_at->format('d M Y') }}</div>
            <div style="margin-top:6px;"><span class="status">{{ $paid ? 'LUNAS' : 'BELUM DIBAYAR' }}</span></div>
        </td>
    </tr>
</table>

<br>

<table class="items">
    <thead>
        <tr>
            <th>Produk</th>
            <th class="right">Harga</th>
            <th class="right">Qty</th>
            <th class="right">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>
                    {{ $item->product_name }}
                    @if($item->product_sku)<br><span class="muted">SKU: {{ $item->product_sku }}</span>@endif
                </td>
                <td class="right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<br>

<table class="totals">
    <tr>
        <td style="width:60%"></td>
        <td>Subtotal</td>
        <td class="right">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td>
    </tr>
    @if($order->discount > 0)
    <tr>
        <td></td>
        <td>Diskon{{ $order->voucher_code ? ' (' . $order->voucher_code . ')' : '' }}</td>
        <td class="right">- Rp {{ number_format($order->discount, 0, ',', '.') }}</td>
    </tr>
    @endif
    <tr>
        <td></td>
        <td>Ongkir{{ $order->shipping_method ? ' (' . $order->shipping_method . ($order->shipping_etd ? ', ' . $order->shipping_etd : '') . ')' : '' }}</td>
        <td class="right">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</td>
    </tr>
    <tr class="grand">
        <td></td>
        <td>Total</td>
        <td class="right">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
    </tr>
</table>

@if($order->note)
    <p class="muted">Catatan: {{ $order->note }}</p>
@endif

</body>
</html>
