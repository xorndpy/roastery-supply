@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
@php
    $tabs = [
        'all' => 'Semua', 'menunggu_bayar' => 'Menunggu Bayar', 'dikonfirmasi' => 'Dikonfirmasi', 'diproses' => 'Diproses',
        'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal', 'terlambat' => 'Terlambat',
    ];
    $pill = [
        'menunggu_bayar' => 'bg-yellow-100 text-yellow-800', 'dikonfirmasi' => 'bg-blue-100 text-blue-800',
        'diproses' => 'bg-purple-100 text-purple-800', 'dikirim' => 'bg-indigo-100 text-indigo-800',
        'selesai' => 'bg-green-100 text-green-800', 'batal' => 'bg-red-100 text-red-800',
    ];
    $canMoney = auth()->user()->can('view_finance');
    $current = request('status') ?: 'all';
    $sortLink = function ($col) {
        $dir = request('sort') === $col && request('dir') !== 'asc' ? 'asc' : 'desc';
        return route('admin.orders.index', array_merge(request()->except('page'), ['sort' => $col, 'dir' => $dir]));
    };
    $arrow = fn ($col) => request('sort', 'created_at') === $col ? (request('dir') === 'asc' ? '↑' : '↓') : '';
    $filterCount = collect(['payment_status', 'payment_method', 'courier', 'min_total', 'max_total', 'from', 'to'])->filter(fn ($k) => request()->filled($k))->count();
@endphp


<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold text-stone-900">Pesanan</h1>
    @can('export_reports')
        <a href="{{ route('admin.orders.export', request()->query()) }}" class="border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 px-4 py-2 rounded-lg text-sm font-medium">Export Excel</a>
    @endcan
</div>

{{-- Filter --}}
<form method="GET" id="filterForm" class="bg-stone-50 border border-stone-200 rounded-2xl p-3 mb-5">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
    <div class="flex flex-wrap items-center gap-2">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari no. pesanan, nama, atau telepon"
               class="flex-1 min-w-[220px] bg-white border border-stone-200 rounded-xl px-4 py-2.5 text-sm focus:border-copper-500 focus:ring-0">
        <select name="payment_status" class="bg-white border border-stone-200 rounded-xl px-3 py-2.5 text-sm">
            <option value="">Status bayar</option>
            @foreach(['belum_bayar' => 'Belum bayar', 'pending' => 'Menunggu verifikasi', 'verified' => 'Lunas', 'rejected' => 'Ditolak'] as $k => $v)
                <option value="{{ $k }}" @selected(request('payment_status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="payment_method" class="bg-white border border-stone-200 rounded-xl px-3 py-2.5 text-sm">
            <option value="">Metode bayar</option>
            @foreach($paymentMethods as $k => $v)<option value="{{ $k }}" @selected(request('payment_method') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="courier" class="bg-white border border-stone-200 rounded-xl px-3 py-2.5 text-sm">
            <option value="">Ekspedisi</option>
            @foreach($couriers as $c)<option value="{{ $c }}" @selected(request('courier') === $c)>{{ strtoupper($c) }}</option>@endforeach
        </select>
        <button type="button" onclick="document.getElementById('moreFilters').classList.toggle('hidden')" class="bg-white border border-stone-200 rounded-xl px-3 py-2.5 text-sm">
            Filter lain @if($filterCount)<span class="ml-1 text-xs bg-copper-500 text-white rounded-full px-1.5">{{ $filterCount }}</span>@endif
        </button>
        <button class="bg-stone-900 text-white rounded-xl px-4 py-2.5 text-sm font-medium">Terapkan</button>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-stone-500 px-2">Reset</a>
    </div>
    <div id="moreFilters" class="{{ request()->anyFilled(['min_total', 'max_total', 'from', 'to']) ? '' : 'hidden' }} grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
        <label class="text-xs text-stone-500">Dari tanggal<input type="date" name="from" value="{{ request('from') }}" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
        <label class="text-xs text-stone-500">Sampai tanggal<input type="date" name="to" value="{{ request('to') }}" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
        @if($canMoney)
            <label class="text-xs text-stone-500">Nominal minimal (Rp)<input type="number" min="0" name="min_total" value="{{ request('min_total') }}" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
            <label class="text-xs text-stone-500">Nominal maksimal (Rp)<input type="number" min="0" name="max_total" value="{{ request('max_total') }}" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
        @endif
    </div>
</form>

{{-- Tab status --}}
<div class="flex flex-wrap gap-x-6 gap-y-1 border-b border-stone-200 mb-3">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.orders.index', array_merge(request()->except(['status', 'page']), $key === 'all' ? [] : ['status' => $key])) }}"
           class="pb-3 text-sm font-medium border-b-2 -mb-px {{ $current === $key ? 'border-copper-500 text-copper-600' : 'border-transparent text-stone-500 hover:text-stone-800' }}">
            {{ $label }}
            <span class="ml-1 text-xs rounded-md px-1.5 py-0.5 {{ $key === 'terlambat' && $stats[$key] > 0 ? 'bg-red-100 text-red-700' : 'bg-stone-100 text-stone-600' }}">{{ $stats[$key] }}</span>
        </a>
    @endforeach
</div>

{{-- Info hasil + per halaman --}}
<div class="flex flex-wrap items-center justify-between gap-2 mb-2 text-xs text-stone-500">
    <span>Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} pesanan</span>
    <form method="GET" class="flex items-center gap-2">
        @foreach(request()->except(['per_page', 'page']) as $k => $v)@if(is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
        <label for="per_page">Per halaman</label>
        <select id="per_page" name="per_page" onchange="this.form.submit()" class="border border-stone-200 rounded-lg px-2 py-1 text-sm bg-white">
            @foreach(config('orders.per_page_options') as $n)<option value="{{ $n }}" @selected((int) request('per_page') === $n)>{{ $n }}</option>@endforeach
        </select>
    </form>
</div>

{{-- Bulk bar --}}
<form method="POST" action="{{ route('admin.orders.bulk') }}" id="bulkForm">
    @csrf
    <div id="bulkBar" class="hidden sticky top-2 z-20 mb-2 flex flex-wrap items-center gap-3 bg-stone-900 text-white rounded-xl px-4 py-2.5 text-sm shadow-lg">
        <span><b id="bulkCount">0</b> dipilih</span>
        <select name="action" required class="bg-stone-800 border border-stone-700 rounded-lg px-3 py-1.5 text-sm">
            <option value="">Pilih aksi</option>
            @can('process_orders')<option value="process">Tandai diproses</option>@endcan
            @can('complete_orders')<option value="complete">Tandai selesai</option>@endcan
            @can('cancel_orders')<option value="cancel">Batalkan</option>@endcan
        </select>
        <input type="text" name="reason" placeholder="Alasan (untuk pembatalan)" class="bg-stone-800 border border-stone-700 rounded-lg px-3 py-1.5 text-sm">
        <button type="submit" onclick="return confirm('Jalankan aksi untuk pesanan yang dipilih?')" class="bg-copper-500 hover:bg-copper-600 rounded-lg px-4 py-1.5 font-medium">Jalankan</button>
    </div>

    {{-- Tabel --}}
    <div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-xs">
                <tr>
                    <th class="px-4 py-3 w-10"><input type="checkbox" id="checkAll" class="rounded border-stone-300" aria-label="Pilih semua"></th>
                    <th class="px-3 py-3 text-left font-medium"><a href="{{ $sortLink('order_number') }}">No. Pesanan {{ $arrow('order_number') }}</a></th>
                    <th class="px-3 py-3 text-left font-medium">Pelanggan</th>
                    <th class="px-3 py-3 text-left font-medium"><a href="{{ $sortLink('status') }}">Status {{ $arrow('status') }}</a></th>
                    <th class="px-3 py-3 text-left font-medium">Pembayaran</th>
                    <th class="px-3 py-3 text-left font-medium">Pengiriman</th>
                    <th class="px-3 py-3 text-left font-medium"><a href="{{ $sortLink('created_at') }}">Tanggal {{ $arrow('created_at') }}</a></th>
                    @if($canMoney)<th class="px-3 py-3 text-right font-medium"><a href="{{ $sortLink('total') }}">Total {{ $arrow('total') }}</a></th>@endif
                    <th class="px-3 py-3 text-right font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($orders as $order)
                    <tr class="hover:bg-stone-50 {{ $order->is_late ? 'bg-red-50/40' : '' }}">
                        <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $order->id }}" class="row-check rounded border-stone-300" aria-label="Pilih {{ $order->order_number }}"></td>
                        <td class="px-3 py-3 font-medium text-stone-900">{{ $order->order_number }}
                            <div class="text-xs font-normal text-stone-400">{{ $order->items->sum('quantity') }} item</div>
                        </td>
                        <td class="px-3 py-3"><div>{{ $order->customer->name ?? 'Guest' }}</div><div class="text-xs text-stone-500">{{ $order->customer->phone ?? '-' }}</div></td>
                        <td class="px-3 py-3">
                            <span class="text-xs px-2.5 py-1 rounded-md font-medium {{ $pill[$order->status] ?? 'bg-stone-100 text-stone-700' }}">{{ $order->status_label }}</span>
                            @if($order->is_late)<div class="mt-1 text-xs text-red-600 font-medium">Terlambat {{ $order->late_hours }} jam</div>@endif
                        </td>
                        <td class="px-3 py-3 text-xs text-stone-600">
                            @if($order->payment)
                                <div class="font-medium text-stone-800">{{ $order->payment->statusLabel() }}</div>{{ $order->payment->methodLabel() }}
                            @else <span class="text-stone-400">Belum bayar</span> @endif
                        </td>
                        <td class="px-3 py-3 text-xs text-stone-600">
                            @if($order->shipment?->courier)
                                <div class="font-medium text-stone-800">{{ strtoupper($order->shipment->courier) }}</div>{{ $order->shipment->tracking_number ?: '-' }}
                            @else <span class="text-stone-400">-</span> @endif
                        </td>
                        <td class="px-3 py-3 text-xs text-stone-600 whitespace-nowrap">{{ wib($order->created_at, 'd M Y', true) }}<div class="text-stone-400">{{ wib($order->created_at, 'H:i', true) }}</div></td>
                        @if($canMoney)<td class="px-3 py-3 text-right font-semibold whitespace-nowrap">Rp {{ number_format($order->total, 0, ',', '.') }}</td>@endif
                        <td class="px-3 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.orders.show', $order->id) }}" class="inline-block border border-copper-500 text-copper-600 hover:bg-copper-50 rounded-lg px-3 py-1 text-xs font-medium">Kelola</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-14 text-center text-stone-500">Tidak ada pesanan yang cocok dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</form>

<div class="mt-4">{{ $orders->links() }}</div>
@endsection

@push('scripts')
<script>
(function () {
    const all = document.getElementById('checkAll'), bar = document.getElementById('bulkBar'), cnt = document.getElementById('bulkCount');
    const rows = () => Array.from(document.querySelectorAll('.row-check'));
    function sync() {
        const n = rows().filter(c => c.checked).length;
        cnt.textContent = n; bar.classList.toggle('hidden', n === 0);
        all.checked = n > 0 && n === rows().length;
    }
    all.addEventListener('change', () => { rows().forEach(c => c.checked = all.checked); sync(); });
    rows().forEach(c => c.addEventListener('change', sync));
})();
</script>
@endpush
