@extends('layouts.admin')

@section('title', 'Pelanggan')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Pelanggan</h1>
        <p class="text-sm text-stone-500 mt-1">Total belanja dihitung dari pesanan yang pembayarannya sudah dikonfirmasi.</p>
    </div>
    @can('export_customers')
        <a href="{{ route('admin.customers.export', request()->query()) }}" class="border border-stone-300 hover:bg-stone-50 px-5 py-2.5 rounded-lg font-medium">Export CSV</a>
    @endcan
</div>

<div class="flex flex-wrap gap-2 mb-4 text-sm">
    <a href="{{ route('admin.customers.index', ['search' => request('search')]) }}" class="px-3 py-1.5 rounded-full border {{ request('segment') ? 'border-stone-300 text-stone-600' : 'border-stone-900 bg-stone-900 text-white' }}">Semua ({{ $counts['all'] }})</a>
    @foreach($segments as $key => $label)
        <a href="{{ route('admin.customers.index', ['segment' => $key, 'search' => request('search')]) }}"
           class="px-3 py-1.5 rounded-full border {{ request('segment') === $key ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300 text-stone-600' }}">{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex gap-3">
    <input type="hidden" name="segment" value="{{ request('segment') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / email / telepon" class="flex-1 border border-stone-300 rounded-lg px-3 py-2">
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Cari</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Pelanggan</th><th class="px-4 py-3">Kota</th><th class="px-4 py-3 text-right">Belanja</th><th class="px-4 py-3 text-right">Total (Rp)</th><th class="px-4 py-3">Terakhir</th><th class="px-4 py-3">Segmen</th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($customers as $c)
            @php
                $seg = (int) $c->paid_orders_count > 0
                    ? (\Illuminate\Support\Carbon::parse($c->last_order_at)->gte(now()->subDays(90)) ? 'aktif' : 'dormant')
                    : ($c->created_at->gte(now()->subDays(30)) ? 'baru' : 'belum_beli');
                $segColor = ['aktif' => 'bg-green-100 text-green-700', 'dormant' => 'bg-amber-100 text-amber-700', 'baru' => 'bg-blue-100 text-blue-700', 'belum_beli' => 'bg-stone-100 text-stone-600'][$seg];
            @endphp
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.customers.show', $c->id) }}" class="font-medium text-orange-600 hover:underline">{{ $c->name }}</a>
                    <div class="text-xs text-stone-500">{{ $c->email }}{{ $c->phone ? ' · ' . $c->phone : '' }}</div>
                </td>
                <td class="px-4 py-3 text-stone-600">{{ $c->city ?: '-' }}</td>
                <td class="px-4 py-3 text-right">{{ $c->paid_orders_count }}×</td>
                <td class="px-4 py-3 text-right font-medium">{{ number_format((int) $c->total_spent, 0, ',', '.') }}</td>
                <td class="px-4 py-3 text-stone-500">{{ $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->format('d M Y') : '-' }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $segColor }}">{{ $segments[$seg] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Tidak ada pelanggan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $customers->links() }}</div>
@endsection
