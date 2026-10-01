@extends('layouts.admin')

@section('title', 'Laporan Penjualan')

@section('content')

{{-- Header --}}
<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Laporan Penjualan</h1>
        <p class="text-sm text-stone-500 mt-1">Analisis performa penjualan toko.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.reports.export-excel', ['period' => $period]) }}"
           class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            📊 Export Excel
        </a>
        <a href="{{ route('admin.reports.export-pdf', ['period' => $period]) }}"
           target="_blank"
           class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
            📄 Export PDF
        </a>
    </div>
</div>

{{-- Filter Periode --}}
<div class="bg-white p-4 rounded-lg border border-stone-200 mb-6">
    <form method="GET" class="flex flex-wrap gap-2 items-center">
        <span class="text-sm font-medium text-stone-600">Periode:</span>
        @php
            $periods = [
                'today' => 'Hari Ini',
                '7days' => '7 Hari',
                '30days' => '30 Hari',
                'this_month' => 'Bulan Ini',
                'last_month' => 'Bulan Lalu',
                'this_year' => 'Tahun Ini',
            ];
        @endphp
        @foreach($periods as $key => $label)
            <a href="{{ route('admin.reports.index', ['period' => $key]) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-medium transition
                      {{ $period === $key ? 'bg-orange-500 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}">
                {{ $label }}
            </a>
        @endforeach

        <div class="ml-auto text-sm text-stone-500">
            {{ $startDate->format('d M Y') }} — {{ $endDate->format('d M Y') }}
        </div>
    </form>
</div>

{{-- Stat Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs text-stone-500 uppercase">Total Pendapatan</div>
            <div class="text-2xl">💰</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">
            Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs text-stone-500 uppercase">Total Pesanan</div>
            <div class="text-2xl">🛒</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">
            {{ number_format($stats['total_orders']) }}
        </div>
    </div>

    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs text-stone-500 uppercase">Rata-rata Order</div>
            <div class="text-2xl">📊</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">
            Rp {{ number_format($stats['avg_order_value'], 0, ',', '.') }}
        </div>
    </div>

    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs text-stone-500 uppercase">Produk Terjual</div>
            <div class="text-2xl">📦</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">
            {{ number_format($stats['total_products_sold']) }}
        </div>
    </div>

    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs text-stone-500 uppercase">Pelanggan Baru</div>
            <div class="text-2xl">👥</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">
            {{ number_format($stats['new_customers']) }}
        </div>
    </div>

    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-xs text-stone-500 uppercase">Menunggu Bayar</div>
            <div class="text-2xl">⏳</div>
        </div>
        <div class="text-2xl font-bold text-orange-500">
            {{ number_format($stats['pending_orders']) }}
        </div>
    </div>
</div>

{{-- Chart Penjualan --}}
<div class="bg-white p-6 rounded-lg border border-stone-200 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="font-bold">Grafik Penjualan</h2>
        <span class="text-xs text-stone-500">Total: Rp {{ number_format($salesChart->sum('total'), 0, ',', '.') }}</span>
    </div>
    <canvas id="salesChart" height="80"></canvas>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Top Products --}}
    <div class="lg:col-span-2 bg-white p-6 rounded-lg border border-stone-200">
        <h2 class="font-bold mb-4">🏆 Produk Terlaris</h2>

        @if($topProducts->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-stone-200">
                        <tr>
                            <th class="text-left py-2 font-medium text-stone-600 w-12">#</th>
                            <th class="text-left py-2 font-medium text-stone-600">Produk</th>
                            <th class="text-right py-2 font-medium text-stone-600">Terjual</th>
                            <th class="text-right py-2 font-medium text-stone-600">Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach($topProducts as $i => $product)
                            <tr class="hover:bg-stone-50">
                                <td class="py-3 text-stone-500">{{ $i + 1 }}</td>
                                <td class="py-3 font-medium">{{ $product->product_name }}</td>
                                <td class="py-3 text-right">
                                    <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full font-medium">
                                        {{ $product->total_sold }}x
                                    </span>
                                </td>
                                <td class="py-3 text-right font-medium">
                                    Rp {{ number_format($product->total_revenue, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-stone-500 text-sm text-center py-8">Belum ada penjualan di periode ini.</p>
        @endif
    </div>

    {{-- Status Breakdown --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <h2 class="font-bold mb-4">📋 Status Pesanan</h2>

        @php
            $statusLabels = [
                'menunggu_bayar' => ['label' => 'Menunggu Bayar', 'color' => 'yellow'],
                'dikonfirmasi' => ['label' => 'Dikonfirmasi', 'color' => 'blue'],
                'diproses' => ['label' => 'Diproses', 'color' => 'purple'],
                'dikirim' => ['label' => 'Dikirim', 'color' => 'indigo'],
                'selesai' => ['label' => 'Selesai', 'color' => 'green'],
                'batal' => ['label' => 'Batal', 'color' => 'red'],
            ];
        @endphp

        <div class="space-y-3">
            @foreach($statusLabels as $key => $info)
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-{{ $info['color'] }}-500"></span>
                        <span class="text-sm">{{ $info['label'] }}</span>
                    </div>
                    <span class="text-sm font-bold">{{ $orderStatusStats[$key] ?? 0 }}</span>
                </div>
            @endforeach
        </div>

        <div class="border-t border-stone-200 mt-6 pt-6">
            <h3 class="font-bold mb-3 text-sm">💳 Pembayaran</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-stone-600">Menunggu</span>
                    <span class="font-medium text-yellow-600">{{ $paymentStats['pending'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-stone-600">Terverifikasi</span>
                    <span class="font-medium text-green-600">{{ $paymentStats['verified'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-stone-600">Ditolak</span>
                    <span class="font-medium text-red-600">{{ $paymentStats['rejected'] }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($salesChart->pluck('label')),
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: @json($salesChart->pluck('total')),
                borderColor: '#f97316',
                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#f97316',
                pointRadius: 5,
                pointHoverRadius: 7,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + parseInt(context.raw).toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                            if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                            return 'Rp ' + value;
                        }
                    }
                }
            }
        }
    });
</script>
@endpush

@endsection