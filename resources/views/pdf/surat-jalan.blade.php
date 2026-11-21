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
@include('pdf._surat-jalan-body', ['order' => $order])
</body>
</html>
