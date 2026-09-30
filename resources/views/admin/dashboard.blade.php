@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')

{{-- Stat Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    {{-- Revenue --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm text-stone-500">Total Pendapatan</div>
            <div class="text-2xl">💰</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">
            Rp {{ number_format($totalRevenue, 0, ',', '.') }}
        </div>
    </div>

    {{-- Orders --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm text-stone-500">Total Pesanan</div>
            <div class="text-2xl">🛒</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">{{ number_format($totalOrders) }}</div>
        @if($pendingOrders > 0)
            <div class="text-xs text-orange-600 mt-1">
                {{ $pendingOrders }} menunggu bayar
            </div>
        @endif
    </div>

    {{-- Products --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm text-stone-500">Produk Aktif</div>
            <div class="text-2xl">📦</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">{{ number_format($totalProducts) }}</div>
        @if($lowStockProducts > 0)
            <div class="text-xs text-red-600 mt-1">
                {{ $lowStockProducts }} stok menipis
            </div>
        @endif
    </div>

    {{-- Customers --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm text-stone-500">Total Pelanggan</div>
            <div class="text-2xl">👥</div>
        </div>
        <div class="text-2xl font-bold text-stone-900">{{ number_format($totalCustomers) }}</div>
        @if($pendingPayments > 0)
            <div class="text-xs text-blue-600 mt-1">
                {{ $pendingPayments }} pembayaran pending
            </div>
        @endif
    </div>
</div>

{{-- Chart --}}
<div class="bg-white p-6 rounded-lg border border-stone-200 mb-8">
    <h2 class="font-bold mb-4">Penjualan 7 Hari Terakhir</h2>
    <canvas id="salesChart" height="80"></canvas>
</div>

{{-- 2 Column --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- Recent Orders --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Pesanan Terbaru</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-xs text-orange-500 hover:text-orange-600">
                Lihat Semua →
            </a>
        </div>
        @if($recentOrders->count())
            <div class="space-y-3">
                @foreach($recentOrders as $order)
                    <a href="{{ route('admin.orders.show', $order->id) }}"
                       class="flex justify-between items-center p-3 hover:bg-stone-50 rounded-lg transition">
                        <div>
                            <div class="font-medium text-sm">{{ $order->order_number }}</div>
                            <div class="text-xs text-stone-500">
                                {{ $order->customer->name ?? 'Guest' }} ·
                                {{ $order->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium text-sm">
                                Rp {{ number_format($order->total, 0, ',', '.') }}
                            </div>
                            <div class="text-xs text-stone-500">{{ $order->status }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-stone-500 text-sm">Belum ada pesanan.</p>
        @endif
    </div>

    {{-- Low Stock --}}
    <div class="bg-white p-6 rounded-lg border border-stone-200">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold">Stok Menipis</h2>
            <a href="{{ route('admin.stock.index') }}" class="text-xs text-orange-500 hover:text-orange-600">
                Kelola Stok →
            </a>
        </div>
        @if($lowStockList->count())
            <div class="space-y-3">
                @foreach($lowStockList as $product)
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                        <div>
                            <div class="font-medium text-sm">{{ $product->name }}</div>
                            <div class="text-xs text-stone-500">{{ $product->sku }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-red-600">{{ $product->stock }}</div>
                            <div class="text-xs text-stone-500">min: {{ $product->stock_threshold }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-stone-500 text-sm">Semua stok aman. 👍</p>
        @endif
    </div>
</div>

@push('scripts')
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($salesChart->pluck('date')),
            datasets: [{
                label: 'Penjualan (Rp)',
                data: @json($salesChart->pluck('total')),
                borderColor: '#f97316',
                backgroundColor: 'rgba(249, 115, 22, 0.1)',
                tension: 0.4,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
</script>
@endpush
@endsection