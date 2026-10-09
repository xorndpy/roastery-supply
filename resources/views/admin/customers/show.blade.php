@extends('layouts.admin')

@section('title', $customer->name)

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.customers.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Pelanggan</a>
    <div class="flex flex-wrap items-center gap-3 mt-2">
        <h1 class="text-2xl font-bold text-stone-900">{{ $customer->name }}</h1>
        <span class="px-2.5 py-1 rounded text-xs bg-stone-100 text-stone-700">{{ $segments[$segment] }}</span>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-white p-4 rounded-lg border border-stone-200"><div class="text-xs text-stone-500">Total Belanja</div><div class="text-xl font-bold mt-1">Rp {{ number_format($summary['total_spent'], 0, ',', '.') }}</div></div>
    <div class="bg-white p-4 rounded-lg border border-stone-200"><div class="text-xs text-stone-500">Pesanan Dibayar</div><div class="text-xl font-bold mt-1">{{ $summary['paid_orders'] }}</div></div>
    <div class="bg-white p-4 rounded-lg border border-stone-200"><div class="text-xs text-stone-500">Semua Pesanan</div><div class="text-xl font-bold mt-1">{{ $summary['orders'] }}</div></div>
    <div class="bg-white p-4 rounded-lg border border-stone-200"><div class="text-xs text-stone-500">Belanja Terakhir</div><div class="text-xl font-bold mt-1">{{ $summary['last_order_at'] ? \Illuminate\Support\Carbon::parse($summary['last_order_at'])->format('d M Y') : '-' }}</div></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white p-6 rounded-lg border border-stone-200 text-sm space-y-1 h-fit">
        <h2 class="font-bold mb-2">Kontak</h2>
        <div>{{ $customer->email }}</div>
        <div>{{ $customer->phone ?: '-' }}</div>
        <div class="text-stone-600 pt-2">{{ $customer->address ?: '-' }}</div>
        <div class="text-stone-600">{{ collect([$customer->city, $customer->province, $customer->postal_code])->filter()->implode(', ') }}</div>
        <div class="text-xs text-stone-400 pt-2">Terdaftar {{ $customer->created_at->format('d M Y') }}</div>
    </div>

    <div class="lg:col-span-2 bg-white rounded-lg border border-stone-200 overflow-x-auto">
        <h2 class="font-bold p-4 border-b border-stone-100">Riwayat Pesanan</h2>
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">No. Pesanan</th><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th></tr></thead>
            <tbody class="divide-y divide-stone-100">
            @forelse($orders as $o)
                <tr>
                    <td class="px-4 py-3">@can('view_orders')<a href="{{ route('admin.orders.show', $o->id) }}" class="font-mono text-orange-600 hover:underline">{{ $o->order_number }}</a>@else<span class="font-mono">{{ $o->order_number }}</span>@endcan</td>
                    <td class="px-4 py-3 text-stone-500">{{ $o->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $o->status) }}</td>
                    <td class="px-4 py-3 text-right">Rp {{ number_format($o->total, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-stone-500">Belum ada pesanan.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
