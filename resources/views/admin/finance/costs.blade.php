@extends('layouts.admin')

@section('title', 'HPP Produk')

@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">HPP Produk</h1>
        <p class="text-sm text-stone-500 mt-1">Harga pokok per unit. Nilainya disalin ke pesanan baru saat checkout, jadi mengubah HPP tidak mengubah laba pesanan yang sudah lewat.</p>
    </div>
    @can('view_profit')<a href="{{ route('admin.finance.profit') }}" class="border border-stone-300 px-4 py-2 rounded-lg text-sm hover:bg-stone-50">Lihat laba rugi</a>@endcan
</div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="flex flex-wrap gap-2 mb-4 text-sm">
    <a href="{{ route('admin.finance.costs', ['search' => $search ?: null]) }}" class="px-3 py-1.5 rounded-full border {{ $filter ? 'border-stone-300 text-stone-600' : 'border-stone-900 bg-stone-900 text-white' }}">Semua</a>
    <a href="{{ route('admin.finance.costs', ['filter' => 'kosong', 'search' => $search ?: null]) }}" class="px-3 py-1.5 rounded-full border {{ $filter === 'kosong' ? 'border-stone-900 bg-stone-900 text-white' : 'border-stone-300 text-stone-600' }}">HPP kosong ({{ $emptyCount }})</a>
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex gap-3">
    <input type="hidden" name="filter" value="{{ $filter }}">
    <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama / SKU" class="flex-1 border border-stone-300 rounded-lg px-3 py-2">
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Cari</button>
</form>

<form method="POST" action="{{ route('admin.finance.costs.update') }}">
    @csrf @method('PUT')
    <div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-500">
                <tr><th class="px-4 py-3">Produk</th><th class="px-4 py-3 text-right">Harga jual</th><th class="px-4 py-3">HPP / unit (Rp)</th><th class="px-4 py-3 text-right">Margin</th><th class="px-4 py-3 text-right">Terjual tanpa HPP</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse($products as $p)
                @php $margin = $p->cost_price !== null && $p->price > 0 ? round(($p->price - $p->cost_price) / $p->price * 100, 1) : null; @endphp
                <tr data-hpp-row data-price="{{ (float) $p->price }}">
                    <td class="px-4 py-2.5"><div class="font-medium">{{ $p->name }}</div><div class="text-xs text-stone-500">{{ $p->sku }}</div></td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">{{ $rp($p->price) }}</td>
                    <td class="px-4 py-2.5">
                        <input type="text" inputmode="numeric" data-rupiah name="costs[{{ $p->id }}]" value="{{ old('costs.' . $p->id, $p->cost_price !== null ? (float) $p->cost_price : '') }}" placeholder="belum diisi"
                               @cannot('manage_costs') readonly @endcannot class="w-40 border border-stone-300 rounded-lg px-3 py-1.5">
                        <p data-hpp-warn class="mt-1 hidden max-w-[16rem] text-xs text-amber-700" role="status"></p>
                    </td>
                    <td data-hpp-margin class="px-4 py-2.5 text-right whitespace-nowrap {{ $margin !== null && $margin < 0 ? 'text-red-600' : '' }}">{{ $margin === null ? '-' : number_format($margin, 1, ',', '.') . '%' }}</td>
                    <td class="px-4 py-2.5 text-right">{{ (int) $p->qty_no_cost > 0 ? (int) $p->qty_no_cost : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Tidak ada produk.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @can('manage_costs')
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input type="checkbox" name="apply_to_past" value="1" class="rounded border-stone-300">
                Terapkan juga ke pesanan lama yang belum punya HPP
            </label>
            <button class="bg-stone-900 hover:bg-stone-800 text-white px-6 py-2.5 rounded-lg font-medium">Simpan HPP</button>
        </div>
    @endcan
</form>

<div class="mt-4">{{ $products->links() }}</div>
@endsection

@push('scripts')
<script>
(function () {
    // Peringatan lunak (tidak memblokir simpan): HPP janggal biasanya salah ketik.
    function check(row) {
        const input = row.querySelector('input[data-rupiah]'), warn = row.querySelector('[data-hpp-warn]'), cell = row.querySelector('[data-hpp-margin]');
        const price = parseFloat(row.dataset.price), digits = input.value.replace(/\D/g, '');
        let msg = '';
        if (digits === '') { cell.textContent = '-'; cell.classList.remove('text-red-600'); }
        else {
            const cost = parseInt(digits, 10), margin = price > 0 ? (price - cost) / price * 100 : null;
            cell.textContent = margin === null ? '-' : margin.toFixed(1).replace('.', ',') + '%';
            cell.classList.toggle('text-red-600', margin !== null && margin < 0);
            if (cost === 0) msg = 'HPP 0: pastikan memang gratis.';
            else if (cost > price) msg = 'HPP lebih besar dari harga jual, margin minus.';
            else if (cost < price * 0.1) msg = 'HPP di bawah 10% harga jual. Cek lagi, mungkin kurang nol.';
        }
        warn.textContent = msg; warn.classList.toggle('hidden', msg === '');
        input.classList.toggle('border-amber-400', msg !== ''); input.classList.toggle('border-stone-300', msg === '');
    }
    document.querySelectorAll('tr[data-hpp-row]').forEach(function (row) {
        row.querySelector('input[data-rupiah]').addEventListener('input', function () { check(row); });
        check(row);
    });
})();
</script>
@endpush
