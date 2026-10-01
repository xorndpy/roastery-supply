<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan - Roastery Supply</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #f97316;
            padding-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            color: #f97316;
            letter-spacing: 1px;
        }
        .header p {
            margin: 4px 0 0;
            color: #666;
            font-size: 11px;
        }
        .header .period {
            font-weight: bold;
            color: #333;
            margin-top: 6px;
        }

        /* Stats */
        .stats {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }
        .stat {
            display: table-cell;
            padding: 10px 8px;
            background: #f9f9f9;
            border: 1px solid #eee;
            text-align: center;
            border-radius: 4px;
            width: 25%;
        }
        .stat-label {
            font-size: 9px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            margin-top: 4px;
            color: #f97316;
        }

        /* Section title */
        h3 {
            font-size: 13px;
            color: #333;
            margin: 18px 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #f97316;
        }

        /* Data table */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 10px;
        }
        table.data thead th {
            background: #f97316;
            color: white;
            padding: 7px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
        }
        table.data tbody td {
            padding: 6px;
            border-bottom: 1px solid #eee;
        }
        table.data tbody tr:nth-child(even) {
            background: #fafafa;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-bold { font-weight: bold; }

        /* Footer */
        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #999;
        }

        /* Empty state */
        .empty {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
    <h1>ROASTERY SUPPLY</h1>
    <p>Laporan Penjualan</p>
    <p class="period">
        Periode: {{ $startDate->format('d F Y') }} — {{ $endDate->format('d F Y') }}
    </p>
</div>

{{-- STATS --}}
<div class="stats">
    <div class="stat">
        <div class="stat-label">Total Pendapatan</div>
        <div class="stat-value">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Total Pesanan</div>
        <div class="stat-value">{{ number_format($stats['total_orders']) }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Rata-rata Order</div>
        <div class="stat-value">Rp {{ number_format($stats['avg_order_value'], 0, ',', '.') }}</div>
    </div>
    <div class="stat">
        <div class="stat-label">Produk Terjual</div>
        <div class="stat-value">{{ number_format($stats['total_products_sold']) }}</div>
    </div>
</div>

{{-- TOP PRODUCTS --}}
<h3>🏆 Produk Terlaris</h3>
@if($topProducts->count())
    <table class="data">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Produk</th>
                <th class="text-center" style="width: 80px;">Terjual</th>
                <th class="text-right" style="width: 120px;">Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topProducts as $i => $product)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $product->product_name }}</td>
                    <td class="text-center">{{ $product->total_sold }}x</td>
                    <td class="text-right font-bold">
                        Rp {{ number_format($product->total_revenue, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="empty">Belum ada penjualan di periode ini.</p>
@endif

{{-- DETAIL ORDERS --}}
<h3>📋 Detail Pesanan</h3>
@if($orders->count())
    <table class="data">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>No. Order</th>
                <th style="width: 80px;">Tanggal</th>
                <th>Customer</th>
                <th class="text-right" style="width: 100px;">Total</th>
                <th class="text-center" style="width: 90px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $i => $order)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td class="font-bold">{{ $order->order_number }}</td>
                    <td>{{ $order->created_at->format('d/m/Y') }}</td>
                    <td>{{ $order->customer->name ?? 'Guest' }}</td>
                    <td class="text-right font-bold">
                        Rp {{ number_format($order->total, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #fef3e8;">
                <td colspan="4" class="text-right font-bold" style="padding: 8px;">
                    TOTAL:
                </td>
                <td class="text-right font-bold" style="padding: 8px; color: #f97316; font-size: 12px;">
                    Rp {{ number_format($orders->sum('total'), 0, ',', '.') }}
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
@else
    <p class="empty">Belum ada pesanan di periode ini.</p>
@endif

{{-- FOOTER --}}
<div class="footer">
    <p>Dicetak pada: {{ now()->format('d F Y, H:i') }}</p>
    <p style="margin-top: 4px;">© {{ date('Y') }} Roastery Supply — Laporan Penjualan</p>
</div>

</body>
</html>