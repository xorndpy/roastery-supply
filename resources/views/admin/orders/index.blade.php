@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')

{{-- Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
    @php
        $statusList = [
            'all' => ['label' => 'Semua', 'color' => 'bg-stone-100 text-stone-800'],
            'menunggu_bayar' => ['label' => 'Menunggu Bayar', 'color' => 'bg-yellow-100 text-yellow-800'],
            'dikonfirmasi' => ['label' => 'Dikonfirmasi', 'color' => 'bg-blue-100 text-blue-800'],
            'diproses' => ['label' => 'Diproses', 'color' => 'bg-purple-100 text-purple-800'],
            'dikirim' => ['label' => 'Dikirim', 'color' => 'bg-indigo-100 text-indigo-800'],
            'selesai' => ['label' => 'Selesai', 'color' => 'bg-green-100 text-green-800'],
            'batal' => ['label' => 'Batal', 'color' => 'bg-red-100 text-red-800'],
        ];
    @endphp
    @foreach($statusList as $key => $s)
        <a href="{{ route('admin.orders.index', $key === 'all' ? [] : ['status' => $key]) }}"
           class="bg-white p-3 rounded-lg border border-stone-200 hover:border-orange-500 transition
                  {{ request('status') == $key || ($key === 'all' && !request('status')) ? 'border-orange-500 ring-2 ring-orange-200' : '' }}">
            <div class="text-xs text-stone-500">{{ $s['label'] }}</div>
            <div class="text-xl font-bold mt-1">{{ $stats[$key] }}</div>
        </a>
    @endforeach
</div>

{{-- Filter --}}
<div class="bg-white p-4 rounded-lg border border-stone-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Cari no. pesanan atau nama..."
               class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
        <input type="date" name="from" value="{{ request('from') }}"
               class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
        <input type="date" name="to" value="{{ request('to') }}"
               class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
        <div class="flex gap-2">
            <button type="submit"
                    class="flex-1 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                Filter
            </button>
            <a href="{{ route('admin.orders.index') }}"
               class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-50">
                Reset
            </a>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="bg-white rounded-lg border border-stone-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 border-b border-stone-200">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-stone-600">No. Pesanan</th>
                <th class="px-4 py-3 text-left font-medium text-stone-600">Pelanggan</th>
                <th class="px-4 py-3 text-left font-medium text-stone-600">Tanggal</th>
                <th class="px-4 py-3 text-right font-medium text-stone-600">Total</th>
                <th class="px-4 py-3 text-center font-medium text-stone-600">Status</th>
                <th class="px-4 py-3 text-right font-medium text-stone-600">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-200">
            @forelse($orders as $order)
                <tr class="hover:bg-stone-50">
                    <td class="px-4 py-3 font-medium">{{ $order->order_number }}</td>
                    <td class="px-4 py-3">
                        <div>{{ $order->customer->name ?? 'Guest' }}</div>
                        <div class="text-xs text-stone-500">{{ $order->customer->phone ?? '-' }}</div>
                    </td>
                    <td class="px-4 py-3 text-stone-600">{{ $order->created_at->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3 text-right font-medium">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                    <td class="px-4 py-3 text-center">
                        @php
                            $badges = [
                                'menunggu_bayar' => 'bg-yellow-100 text-yellow-800',
                                'dikonfirmasi' => 'bg-blue-100 text-blue-800',
                                'diproses' => 'bg-purple-100 text-purple-800',
                                'dikirim' => 'bg-indigo-100 text-indigo-800',
                                'selesai' => 'bg-green-100 text-green-800',
                                'batal' => 'bg-red-100 text-red-800',
                            ];
                            $labels = [
                                'menunggu_bayar' => 'Menunggu Bayar',
                                'dikonfirmasi' => 'Dikonfirmasi',
                                'diproses' => 'Diproses',
                                'dikirim' => 'Dikirim',
                                'selesai' => 'Selesai',
                                'batal' => 'Batal',
                            ];
                        @endphp
                        <span class="text-xs px-2 py-1 rounded-full {{ $badges[$order->status] ?? 'bg-stone-100' }}">
                            {{ $labels[$order->status] ?? $order->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.orders.show', $order->id) }}"
                           class="text-orange-500 hover:text-orange-600 font-medium text-sm">
                            Detail →
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-stone-500">
                        Belum ada pesanan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $orders->links() }}
</div>
@endsection