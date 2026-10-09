@extends('layouts.admin')

@section('title', 'Riwayat Stok')

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.stock.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Stok</a>
    <h1 class="text-2xl font-bold text-stone-900 mt-2">Riwayat Mutasi Stok</h1>
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari produk / SKU"
           class="flex-1 min-w-[200px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="type" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Jenis</option>
        <option value="in" @selected(request('type') === 'in')>Masuk</option>
        <option value="out" @selected(request('type') === 'out')>Keluar</option>
        <option value="adjustment" @selected(request('type') === 'adjustment')>Opname</option>
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="border border-stone-300 rounded-lg px-3 py-2">
    <input type="date" name="to" value="{{ request('to') }}" class="border border-stone-300 rounded-lg px-3 py-2">
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr>
                <th class="px-4 py-3">Waktu</th>
                <th class="px-4 py-3">Produk</th>
                <th class="px-4 py-3">Jenis</th>
                <th class="px-4 py-3 text-right">Jumlah</th>
                <th class="px-4 py-3">Referensi</th>
                <th class="px-4 py-3">Catatan</th>
                <th class="px-4 py-3">Oleh</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($movements as $m)
            @php
                $sign = $m->type === 'out' ? '−' : ($m->quantity < 0 ? '−' : '+');
                $color = $sign === '−' ? 'text-red-600' : 'text-green-600';
            @endphp
            <tr>
                <td class="px-4 py-3 whitespace-nowrap text-stone-500">{{ $m->created_at->format('d M Y H:i') }}</td>
                <td class="px-4 py-3">
                    <div class="font-medium">{{ $m->product->name ?? '(produk dihapus)' }}</div>
                    <div class="text-xs font-mono text-stone-500">{{ $m->product->sku ?? '' }}</div>
                </td>
                <td class="px-4 py-3">{{ ['in' => 'Masuk', 'out' => 'Keluar', 'adjustment' => 'Opname'][$m->type] ?? $m->type }}</td>
                <td class="px-4 py-3 text-right font-bold {{ $color }}">{{ $sign }}{{ abs($m->quantity) }}</td>
                <td class="px-4 py-3">
                    @if(in_array($m->reference_type, ['order', 'order_cancel']) && isset($orderRefs[$m->reference_id]))
                        <a href="{{ route('admin.orders.show', $m->reference_id) }}" class="text-orange-600 hover:underline">{{ $orderRefs[$m->reference_id] }}</a>
                    @else
                        <span class="text-stone-500">{{ ['manual' => 'Manual', 'initial' => 'Stok awal'][$m->reference_type] ?? '-' }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-stone-600">{{ $m->note ?: '-' }}</td>
                <td class="px-4 py-3 text-stone-500">{{ $m->user->name ?? 'Sistem' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-stone-500">Belum ada mutasi stok.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $movements->links() }}</div>

@endsection
