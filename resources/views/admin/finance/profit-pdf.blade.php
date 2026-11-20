<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Laba Rugi - {{ \App\Models\Setting::get('site_name') }}</title>
<style>
  @page { margin: 34px 40px 60px 40px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1c1917; line-height: 1.45; }
  table.t { width: 100%; border-collapse: collapse; margin-top: 14px; }
  .t td, .t th { padding: 6px 4px; border-bottom: 1px solid #e7e5e4; text-align: left; }
  .t th { border-bottom: 1px solid #1c1917; border-top: 1.5px solid #1c1917; }
  .r { text-align: right !important; }
  .b td { font-weight: bold; border-top: 1px solid #1c1917; }
  .foot { margin-top: 24px; font-size: 9.5px; color: #57534e; }
</style>
</head>
<body>
@php $rp = fn ($n) => ($n < 0 ? '- ' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.'); @endphp
@include('pdf._kop', ['title' => 'LAPORAN LABA RUGI', 'subtitle' => 'Periode: ' . $from . ' s/d ' . $to])
<table class="t">
  <tr><th>Keterangan</th><th class="r">Jumlah</th></tr>
  <tr><td>Penjualan produk (kotor)</td><td class="r">{{ $rp($r['gross_sales']) }}</td></tr>
  <tr><td>Diskon voucher</td><td class="r">{{ $rp(-$r['discount']) }}</td></tr>
  <tr><td>Retur / refund disetujui</td><td class="r">{{ $rp(-$r['refund']) }}</td></tr>
  @if($r['service_revenue'] > 0)<tr><td>Jasa servis (berbayar)</td><td class="r">{{ $rp($r['service_revenue']) }}</td></tr>@endif
  @if($r['tax_rate'])<tr><td>PPN keluaran ({{ $r['tax_rate'] }}%, termasuk dalam harga)</td><td class="r">{{ $rp(-$r['ppn']) }}</td></tr>@endif
  <tr class="b"><td>Pendapatan bersih</td><td class="r">{{ $rp($r['revenue']) }}</td></tr>
  <tr><td>HPP</td><td class="r">{{ $rp(-$r['cogs']) }}</td></tr>
  <tr class="b"><td>Laba kotor @if($r['gross_margin'] !== null)(margin {{ number_format($r['gross_margin'], 1, ',', '.') }}%)@endif</td><td class="r">{{ $rp($r['gross_profit']) }}</td></tr>
  @foreach($r['expenses_by_category'] as $cat => $v)
    <tr><td>Biaya: {{ \App\Models\Expense::CATEGORIES[$cat] ?? $cat }}</td><td class="r">{{ $rp(-$v) }}</td></tr>
  @endforeach
  <tr><td>Biaya payment gateway ({{ $r['gateway_count'] }} transaksi)</td><td class="r">{{ $rp(-$r['gateway_fees']) }}</td></tr>
  <tr class="b"><td>Laba bersih @if($r['net_margin'] !== null)(margin {{ number_format($r['net_margin'], 1, ',', '.') }}%)@endif</td><td class="r">{{ $rp($r['net_profit']) }}</td></tr>
</table>

@if(!empty($r['products']))
<table class="t">
  <tr><th>Produk</th><th class="r">Qty</th><th class="r">Penjualan</th><th class="r">HPP</th><th class="r">Laba kotor</th></tr>
  @foreach($r['products'] as $p)
    <tr><td>{{ $p['name'] }}</td><td class="r">{{ $p['qty'] }}</td><td class="r">{{ $rp($p['revenue']) }}</td><td class="r">{{ $rp($p['cogs']) }}</td><td class="r">{{ $rp($p['profit']) }}</td></tr>
  @endforeach
</table>
@endif

@if(($r['missing_cost_qty'] ?? 0) > 0)
  <p class="foot">Catatan: {{ $r['missing_cost_qty'] }} unit terjual belum memiliki HPP sehingga dihitung Rp 0.</p>
@endif
<p class="foot">Dicetak: {{ wib(now(), 'd M Y, H:i') }} WIB</p>
</body>
</html>
