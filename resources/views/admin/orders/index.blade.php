@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
@php
    $tabs = [
        'all' => 'Semua', 'menunggu_bayar' => 'Menunggu Bayar', 'dikonfirmasi' => 'Dikonfirmasi', 'diproses' => 'Diproses',
        'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal', 'terlambat' => 'Terlambat',
    ];
    // Warna status SAMA dengan Dashboard (satu sumber warna supaya admin tidak bingung).
    $statusColors = ['menunggu_bayar' => '#d97706', 'dikonfirmasi' => '#2563eb', 'diproses' => '#7c3aed', 'dikirim' => '#0891b2', 'selesai' => '#16a34a', 'batal' => '#dc2626'];
    $payColors = ['verified' => '#16a34a', 'pending' => '#d97706', 'waiting' => '#a1a1aa', 'rejected' => '#dc2626', 'expired' => '#a1a1aa', 'review' => '#d97706', 'duplicate' => '#d97706', 'refunded' => '#71717a'];
    $canMoney = auth()->user()->can('view_finance');
    $current = request('status') ?: 'all';
    $sortLink = function ($col) {
        $dir = request('sort') === $col && request('dir') !== 'asc' ? 'asc' : 'desc';
        return route('admin.orders.index', array_merge(request()->except('page'), ['sort' => $col, 'dir' => $dir]));
    };
    $arrow = fn ($col) => request('sort', 'created_at') === $col ? (request('dir') === 'asc' ? '↑' : '↓') : '';
    $filterKeys = ['search', 'payment_status', 'payment_method', 'courier', 'min_total', 'max_total', 'from', 'to'];
    $hasFilter = collect($filterKeys)->contains(fn ($k) => request()->filled($k));
    $filterCount = collect(['min_total', 'max_total', 'from', 'to'])->filter(fn ($k) => request()->filled($k))->count();
    $selectCls = 'bg-white border border-stone-200 rounded-xl px-3 py-2.5 text-sm text-stone-700';
@endphp

{{-- Header --}}
<div class="flex flex-wrap items-end justify-between gap-3 mb-5">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Pesanan</h1>
        <p class="mt-0.5 text-sm text-stone-500">Kelola pesanan masuk, pembayaran, dan pengiriman.</p>
    </div>
    @can('export_reports')
        <a href="{{ route('admin.orders.export', request()->query()) }}" class="border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 px-4 py-2 rounded-lg text-sm font-medium">Export Excel</a>
    @endcan
</div>

{{-- Filter: dropdown langsung menerapkan, tanpa klik "Terapkan" --}}
<form method="GET" id="filterForm" class="bg-white border border-stone-200 rounded-2xl p-3 mb-4">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
    @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[240px]">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari no. pesanan, nama, atau telepon (Enter)"
                   class="w-full bg-stone-50 border border-stone-200 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:border-copper-500 focus:bg-white focus:ring-0">
        </div>
        <select name="payment_status" onchange="this.form.submit()" class="{{ $selectCls }}" aria-label="Status bayar">
            <option value="">Semua status bayar</option>
            @foreach(['belum_bayar' => 'Belum bayar', 'pending' => 'Menunggu verifikasi', 'verified' => 'Terkonfirmasi', 'review' => 'Perlu ditinjau', 'rejected' => 'Ditolak', 'expired' => 'Kedaluwarsa', 'refunded' => 'Sudah direfund'] as $k => $v)
                <option value="{{ $k }}" @selected(request('payment_status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="payment_method" onchange="this.form.submit()" class="{{ $selectCls }}" aria-label="Metode bayar">
            <option value="">Semua metode</option>
            @foreach($paymentMethods as $k => $v)<option value="{{ $k }}" @selected(request('payment_method') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="courier" onchange="this.form.submit()" class="{{ $selectCls }}" aria-label="Ekspedisi">
            <option value="">Semua ekspedisi</option>
            @foreach($couriers as $c)<option value="{{ $c }}" @selected(request('courier') === $c)>{{ strtoupper($c) }}</option>@endforeach
        </select>
        <button type="button" onclick="document.getElementById('moreFilters').classList.toggle('hidden')" class="{{ $selectCls }} inline-flex items-center gap-1.5">
            Tanggal &amp; nominal
            @if($filterCount)<span class="text-[11px] bg-copper-500 text-white rounded-full px-1.5 leading-5">{{ $filterCount }}</span>@endif
        </button>
        <button class="bg-stone-900 text-white rounded-xl px-5 py-2.5 text-sm font-medium">Cari</button>
        @if($hasFilter)
            <a href="{{ route('admin.orders.index', array_filter(['status' => request('status')])) }}" class="text-sm font-medium text-copper-600 hover:underline px-1">Hapus filter</a>
        @endif
    </div>
    <div id="moreFilters" class="{{ request()->anyFilled(['min_total', 'max_total', 'from', 'to']) ? '' : 'hidden' }} grid grid-cols-2 md:grid-cols-4 gap-2 mt-3 pt-3 border-t border-stone-100">
        <label class="text-xs text-stone-500">Dari tanggal<input type="date" name="from" value="{{ request('from') }}" onchange="this.form.submit()" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
        <label class="text-xs text-stone-500">Sampai tanggal<input type="date" name="to" value="{{ request('to') }}" onchange="this.form.submit()" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
        @if($canMoney)
            <label class="text-xs text-stone-500">Nominal minimal (Rp)<input type="number" min="0" name="min_total" value="{{ request('min_total') }}" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
            <label class="text-xs text-stone-500">Nominal maksimal (Rp)<input type="number" min="0" name="max_total" value="{{ request('max_total') }}" class="mt-1 w-full bg-white border border-stone-200 rounded-xl px-3 py-2 text-sm"></label>
        @endif
    </div>
</form>

{{-- Tab status: tab kosong diredupkan, bisa di-scroll di HP --}}
<div class="flex gap-x-6 overflow-x-auto whitespace-nowrap border-b border-stone-200 mb-3" role="tablist" aria-label="Filter status pesanan">
    @foreach($tabs as $key => $label)
        @php $n = (int) $stats[$key]; $active = $current === $key; @endphp
        <a href="{{ route('admin.orders.index', array_merge(request()->except(['status', 'page']), $key === 'all' ? [] : ['status' => $key])) }}"
           role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
           class="pb-3 text-sm font-medium border-b-2 -mb-px {{ $active ? 'border-copper-500 text-copper-600' : ($n === 0 ? 'border-transparent text-stone-400 hover:text-stone-600' : 'border-transparent text-stone-600 hover:text-stone-900') }}">
            {{ $label }}
            <span class="ml-1 text-xs rounded-md px-1.5 py-0.5 {{ $key === 'terlambat' && $n > 0 ? 'bg-red-100 text-red-700' : ($n === 0 ? 'bg-stone-50 text-stone-400' : 'bg-stone-100 text-stone-600') }}">{{ $n }}</span>
        </a>
    @endforeach
</div>

{{-- Info hasil + per halaman --}}
<div class="flex flex-wrap items-center justify-between gap-2 mb-2 text-xs text-stone-500">
    <span>Menampilkan {{ $orders->firstItem() ?? 0 }}–{{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} pesanan</span>
    <form method="GET" class="flex items-center gap-2">
        @foreach(request()->except(['per_page', 'page']) as $k => $v)@if(is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
        <label for="per_page">Tampilkan</label>
        <select id="per_page" name="per_page" onchange="this.form.submit()" class="border border-stone-200 rounded-lg pl-2 pr-7 py-1 text-sm bg-white">
            @foreach(config('orders.per_page_options') as $n)<option value="{{ $n }}" @selected((int) request('per_page', 10) === $n)>{{ $n }}</option>@endforeach
        </select>
    </form>
</div>

<form method="POST" action="{{ route('admin.orders.bulk') }}" id="bulkForm">
    @csrf
    {{-- Bulk bar: menempel DI BAWAH topbar (topbar tinggi 56px), alasan hanya muncul saat memilih "Batalkan" --}}
    <div id="bulkBar" class="hidden sticky top-16 z-40 mb-2 flex flex-wrap items-center gap-3 bg-stone-900 text-white rounded-xl px-4 py-2.5 text-sm shadow-lg">
        <span><b id="bulkCount">0</b> pesanan dipilih</span>
        <select name="action" id="bulkAction" required class="bg-stone-800 border border-stone-700 rounded-lg pl-3 pr-8 py-1.5 text-sm">
            <option value="">Pilih aksi…</option>
            @can('process_orders')<option value="process">Tandai diproses</option>@endcan
            @can('complete_orders')<option value="complete">Tandai selesai</option>@endcan
            @can('cancel_orders')<option value="cancel">Batalkan</option>@endcan
        </select>
        <input type="text" name="reason" id="bulkReason" placeholder="Alasan pembatalan (wajib)" class="hidden bg-stone-800 border border-stone-700 rounded-lg px-3 py-1.5 text-sm min-w-[220px]">
        <button type="submit" id="bulkRun" disabled class="bg-copper-500 hover:bg-copper-600 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg px-4 py-1.5 font-medium">Jalankan</button>
        <button type="button" id="bulkClear" class="ml-auto text-stone-300 hover:text-white underline-offset-2 hover:underline">Batal pilih</button>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-xs">
                <tr>
                    <th class="pl-4 pr-1 py-3 w-10"><input type="checkbox" id="checkAll" class="rounded border-stone-300" aria-label="Pilih semua di halaman ini"></th>
                    <th class="px-3 py-3 text-left font-medium"><a href="{{ $sortLink('order_number') }}">No. Pesanan {{ $arrow('order_number') }}</a></th>
                    <th class="px-3 py-3 text-left font-medium">Pelanggan</th>
                    <th class="px-3 py-3 text-left font-medium"><a href="{{ $sortLink('status') }}">Status {{ $arrow('status') }}</a></th>
                    <th class="px-3 py-3 text-left font-medium">Pembayaran</th>
                    <th class="px-3 py-3 text-left font-medium">Pengiriman</th>
                    <th class="px-3 py-3 text-left font-medium"><a href="{{ $sortLink('created_at') }}">Tanggal (WIB) {{ $arrow('created_at') }}</a></th>
                    @if($canMoney)<th class="px-3 py-3 text-right font-medium"><a href="{{ $sortLink('total') }}">Total {{ $arrow('total') }}</a></th>@endif
                    <th class="px-4 py-3 text-right font-medium"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($orders as $order)
                    @php
                        $pay = $order->payment;
                        $needsRefund = $order->status === 'batal' && $pay && $pay->status === 'verified';
                    @endphp
                    <tr data-href="{{ route('admin.orders.show', $order->id) }}" class="order-row cursor-pointer hover:bg-stone-50 {{ $order->is_late ? 'bg-red-50/40' : '' }}">
                        <td class="pl-4 pr-1 py-3.5"><input type="checkbox" name="ids[]" value="{{ $order->id }}" class="row-check rounded border-stone-300" aria-label="Pilih {{ $order->order_number }}"></td>
                        <td class="px-3 py-3.5 font-medium text-stone-900 whitespace-nowrap">
                            <a href="{{ route('admin.orders.show', $order->id) }}" class="hover:text-copper-600">{{ $order->order_number }}</a>
                            <div class="text-xs font-normal text-stone-500">{{ $order->items->sum('quantity') }} item</div>
                        </td>
                        <td class="px-3 py-3.5">
                            <div class="font-medium text-stone-800">{{ $order->customer->name ?? 'Guest' }}</div>
                            <div class="text-xs text-stone-500">{{ $order->customer->phone ?? '-' }}</div>
                        </td>
                        <td class="px-3 py-3.5">
                            <span class="dash-badge" style="--c: {{ $statusColors[$order->status] ?? '#71717a' }}"><i></i>{{ $order->status_label }}</span>
                            @if($order->is_late)<div class="mt-1 text-xs text-red-600 font-medium">Terlambat {{ $order->late_hours }} jam</div>@endif
                        </td>
                        <td class="px-3 py-3.5">
                            @if($pay)
                                <span class="dash-badge" style="--c: {{ $payColors[$pay->status] ?? '#71717a' }}"><i></i>{{ $pay->statusLabel() }}</span>
                                <div class="mt-1 text-xs text-stone-500">{{ $pay->methodLabel() }}</div>
                                @if($needsRefund)<div class="mt-0.5 text-xs font-medium text-amber-700">Sudah dibayar · cek refund</div>@endif
                            @else
                                <span class="text-xs text-stone-500">Belum bayar</span>
                            @endif
                        </td>
                        <td class="px-3 py-3.5 text-xs text-stone-600">
                            @if($order->shipment?->courier)
                                <div class="font-medium text-stone-800">{{ strtoupper($order->shipment->courier) }}</div>
                                <div class="tabular-nums">{{ $order->shipment->tracking_number ?: '-' }}</div>
                            @elseif(in_array($order->status, ['dikonfirmasi', 'diproses'], true))
                                <span class="text-stone-500">Belum dikirim</span>
                            @else
                                <span class="text-stone-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3.5 text-xs text-stone-600 whitespace-nowrap">
                            {{ wib($order->created_at, 'd M Y') }}
                            <div class="text-stone-500">{{ wib($order->created_at, 'H:i') }}</div>
                        </td>
                        @if($canMoney)<td class="px-3 py-3.5 text-right font-semibold whitespace-nowrap tabular-nums">Rp {{ number_format($order->total, 0, ',', '.') }}</td>@endif
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            <a href="{{ route('admin.orders.show', $order->id) }}" class="text-sm font-medium text-copper-600 hover:text-copper-800 hover:underline">Kelola →</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-14 text-center text-stone-500">
                        Tidak ada pesanan yang cocok.
                        @if($hasFilter)<a href="{{ route('admin.orders.index') }}" class="block mt-2 text-copper-600 font-medium hover:underline">Hapus semua filter</a>@endif
                    </td></tr>
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
    const act = document.getElementById('bulkAction'), reason = document.getElementById('bulkReason'), run = document.getElementById('bulkRun');
    const rows = () => Array.from(document.querySelectorAll('.row-check'));
    const labels = { process: 'ditandai diproses', complete: 'ditandai selesai', cancel: 'DIBATALKAN' };

    function sync() {
        const n = rows().filter(c => c.checked).length;
        cnt.textContent = n; bar.classList.toggle('hidden', n === 0);
        all.checked = n > 0 && n === rows().length;
        all.indeterminate = n > 0 && n < rows().length;
        rows().forEach(c => c.closest('tr').classList.toggle('bg-copper-50/60', c.checked));
    }
    function syncAction() {
        const cancel = act.value === 'cancel';
        reason.classList.toggle('hidden', !cancel); reason.required = cancel;
        run.disabled = !act.value;
    }
    all.addEventListener('change', () => { rows().forEach(c => c.checked = all.checked); sync(); });
    rows().forEach(c => c.addEventListener('change', sync));
    act.addEventListener('change', syncAction);
    document.getElementById('bulkClear').addEventListener('click', () => { rows().forEach(c => c.checked = false); sync(); });
    document.getElementById('bulkForm').addEventListener('submit', e => {
        const n = rows().filter(c => c.checked).length;
        if (!confirm(n + ' pesanan akan ' + (labels[act.value] || 'diproses') + '. Lanjutkan?')) e.preventDefault();
    });

    // Klik di mana saja pada baris membuka detail (kecuali checkbox / link).
    document.querySelectorAll('.order-row').forEach(tr => tr.addEventListener('click', e => {
        if (e.target.closest('a, input, button, label')) return;
        window.location = tr.dataset.href;
    }));
    syncAction(); sync();
})();
</script>
@endpush
