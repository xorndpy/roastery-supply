<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Surat Jalan {{ $order->order_number }}</title>
<style>
    /* dompdf: tanpa flex/grid, pakai tabel. Tanpa harga: dokumen ini untuk gudang dan kurir. */
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c1917; }
    h1 { font-size: 20px; margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #78716c; }
    .right { text-align: right; }
    .items th { background: #f5f5f4; text-align: left; padding: 7px 8px; border: 1px solid #d6d3d1; }
    .items td { padding: 9px 8px; border: 1px solid #d6d3d1; vertical-align: top; }
    .box { width: 26px; text-align: center; }
    .sign td { width: 33%; height: 70px; border: 1px solid #d6d3d1; vertical-align: bottom; padding: 6px 8px; font-size: 10px; color: #57534e; }
</style>
</head>
<body>
<table>
    <tr>
        <td>
            <h1>SURAT JALAN</h1>
            <div class="muted">Daftar kemas (packing list)</div>
        </td>
        <td class="right">
            <strong>{{ \App\Models\Setting::get('site_name') }}</strong>
            @if(\App\Models\Setting::get('contact_address'))<br><span class="muted">{{ \App\Models\Setting::get('contact_address') }}</span>@endif
            @if(\App\Models\Setting::get('contact_phone'))<br><span class="muted">{{ \App\Models\Setting::get('contact_phone') }}</span>@endif
        </td>
    </tr>
</table>

<br>

<table>
    <tr>
        <td style="width:55%; vertical-align: top;">
            <div class="muted">Kirim ke</div>
            <strong>{{ $order->shipping_name ?: ($order->customer->name ?? '-') }}</strong><br>
            {{ $order->shipping_phone ?: ($order->customer->phone ?? '') }}<br>
            {{ $order->full_shipping_address ?: ($order->customer->address ?? '-') }}
        </td>
        <td class="right" style="vertical-align: top;">
            <div><span class="muted">No. Pesanan:</span> <strong>{{ $order->order_number }}</strong></div>
            <div><span class="muted">Tanggal pesan:</span> {{ wib($order->created_at, 'd M Y, H:i', true) }}</div>
            <div><span class="muted">Layanan pilihan pelanggan:</span> {{ $order->shipping_method ?: '-' }}@if($order->shipping_etd) ({{ $order->shipping_etd }})@endif</div>
        </td>
    </tr>
</table>

@if($order->note)
    <br>
    <div><span class="muted">Catatan pelanggan:</span> {{ $order->note }}</div>
@endif

<br>

<table class="items">
    <thead>
        <tr>
            <th style="width:28px;">No</th>
            <th>Barang (varian tertulis di nama)</th>
            <th class="right" style="width:50px;">Qty</th>
            <th class="box">Cek</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $item->product_name }}</strong>
                    @if($item->product_sku)<br><span class="muted">SKU: {{ $item->product_sku }}</span>@endif
                </td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="box">&nbsp;</td>
            </tr>
        @endforeach
        <tr>
            <td></td>
            <td class="right"><strong>Total barang</strong></td>
            <td class="right"><strong>{{ $order->items->sum('quantity') }}</strong></td>
            <td></td>
        </tr>
    </tbody>
</table>

<br><br>

<table class="sign">
    <tr>
        <td>Dikemas oleh<br><br><br>Nama &amp; tanggal</td>
        <td>Diserahkan ke kurir<br><br><br>Nama kurir / resi</td>
        <td>Diterima pelanggan<br><br><br>Nama &amp; tanggal</td>
    </tr>
</table>
</body>
</html>
