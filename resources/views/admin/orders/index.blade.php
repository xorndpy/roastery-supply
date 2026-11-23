@extends('layouts.admin')

@section('title', 'Pesanan')

@section('content')
@php
    $tabs = [
        'perlu' => 'Perlu tindakan', 'all' => 'Semua', 'menunggu_bayar' => 'Menunggu Bayar', 'dikonfirmasi' => 'Dikonfirmasi', 'diproses' => 'Diproses',
        'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal', 'terlambat' => 'Terlambat',
    ];
    $pill = [
        'menunggu_bayar' => 'bg-yellow-100 text-yellow-800', 'dikonfirmasi' => 'bg-blue-100 text-blue-800',
        'diproses' => 'bg-purple-100 text-purple-800', 'dikirim' => 'bg-indigo-100 text-indigo-800',
        'selesai' => 'bg-green-100 text-green-800', 'batal' => 'bg-red-100 text-red-800',
    ];
    $canMoney = auth()->user()->can('view_finance');
    $current = request('status') ?: (request()->filled('search') ? 'all' : 'perlu');
    $sortLink = function ($col) {
        $dir = request('sort') === $col && request('dir') !== 'asc' ? 'asc' : 'desc';
        return route('admin.orders.index', array_merge(request()->except('page'), ['sort' => $col, 'dir' => $dir]));
    };
    $arrow = fn ($col) => request('sort', 'created_at') === $col
        ? new \Illuminate\Support\HtmlString('<svg class="ml-1 inline h-3 w-3 align-[-1px] ' . (request('dir') === 'asc' ? 'rotate-180' : '') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>')
        : '';
    $statusLabel = ['belum_bayar' => 'Belum bayar', 'pending' => 'Menunggu verifikasi', 'verified' => 'Lunas', 'rejected' => 'Ditolak'];
    $chips = [];
    if (request()->filled('search')) $chips['search'] = 'Cari: ' . request('search');
    if (request()->filled('payment_status')) $chips['payment_status'] = 'Bayar: ' . ($statusLabel[request('payment_status')] ?? request('payment_status'));
    if (request()->filled('payment_method')) $chips['payment_method'] = 'Metode: ' . ($paymentMethods[request('payment_method')] ?? request('payment_method'));
    if (request()->filled('courier')) $chips['courier'] = 'Ekspedisi: ' . strtoupper(request('courier'));
    if (request()->filled('from')) $chips['from'] = 'Dari ' . request('from');
    if (request()->filled('to')) $chips['to'] = 'Sampai ' . request('to');
    if ($canMoney && request()->filled('min_total')) $chips['min_total'] = 'Min Rp ' . number_format((float) request('min_total'), 0, ',', '.');
    if ($canMoney && request()->filled('max_total')) $chips['max_total'] = 'Maks Rp ' . number_format((float) request('max_total'), 0, ',', '.');
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
               class="flex-1 min-w-[220px] bg-white border border-stone-200 rounded-xl px-4 py-2.5 text-sm focus:border-stone-500 focus:ring-0">
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
            Filter lain @if($filterCount)<span class="ml-1 text-xs bg-stone-900 text-white rounded-full px-1.5">{{ $filterCount }}</span>@endif
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

{{-- Filter aktif --}}
@if($chips)
    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach($chips as $key => $text)
            <a href="{{ route('admin.orders.index', request()->except([$key, 'page'])) }}" class="group inline-flex items-center gap-1.5 rounded-full border border-stone-300 bg-white py-1 pl-3 pr-2 text-xs text-stone-700 hover:border-stone-400" title="Hapus filter ini">
                {{ $text }}
                <svg class="h-3.5 w-3.5 text-stone-400 group-hover:text-stone-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </a>
        @endforeach
        <a href="{{ route('admin.orders.index', request()->only(['status', 'per_page'])) }}" class="text-xs text-stone-500 hover:text-stone-800 underline underline-offset-2">Hapus semua</a>
    </div>
@endif

{{-- Tab status --}}
<div class="flex flex-wrap gap-x-6 gap-y-1 border-b border-stone-200 mb-3">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.orders.index', array_merge(request()->except(['status', 'page']), ['status' => $key])) }}"
           class="pb-3 text-sm font-medium border-b-2 -mb-px {{ $current === $key ? 'border-stone-900 text-stone-900' : 'border-transparent text-stone-500 hover:text-stone-800' }}">
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
        <select id="per_page" name="per_page" onchange="this.form.submit()" class="border border-stone-200 rounded-lg pl-2 pr-8 py-1 text-sm bg-white">
            @foreach(config('orders.per_page_options') as $n)<option value="{{ $n }}" @selected((int) request('per_page') === $n)>{{ $n }}</option>@endforeach
        </select>
    </form>
</div>

{{-- Bulk bar --}}
<form method="POST" action="{{ route('admin.orders.bulk') }}" id="bulkForm">
    @csrf
    <div id="bulkBar" class="hidden sticky top-2 z-20 mb-2 flex flex-wrap items-center gap-3 bg-white text-stone-900 border border-stone-300 rounded-xl px-4 py-2.5 text-sm shadow-sm">
        <span><b id="bulkCount">0</b> dipilih</span>
        <select name="action" required class="bg-white border border-stone-300 rounded-lg px-3 py-1.5 text-sm text-stone-900">
            <option value="">Pilih aksi</option>
            @can('process_orders')<option value="process">Tandai diproses</option>@endcan
            @can('complete_orders')<option value="complete">Tandai selesai</option>@endcan
            @can('ship_orders')<option value="ship">Kirim (resi sudah terisi)</option>@endcan
            @can('cancel_orders')<option value="cancel">Batalkan</option>@endcan
        </select>
        <input type="text" name="reason" id="bulkReason" placeholder="Alasan pembatalan" hidden class="bg-white border border-stone-300 rounded-lg px-3 py-1.5 text-sm text-stone-900">
        <button type="submit" class="bg-stone-900 hover:bg-stone-800 text-white rounded-lg px-4 py-1.5 font-medium">Jalankan</button>
        <button type="button" id="bulkPrint" data-url="{{ route('admin.orders.bulk-delivery-notes') }}" class="border border-stone-300 bg-white hover:bg-stone-50 text-stone-800 rounded-lg px-4 py-1.5 font-medium">Cetak surat jalan</button>
        <button type="button" id="bulkClear" class="ml-auto text-stone-500 hover:text-stone-800 underline underline-offset-2">Batal pilih</button>
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
                    <th class="px-3 py-3 text-left font-medium"><a href="{{ $sortLink('created_at') }}">Tanggal {{ $arrow('created_at') }}</a></th>
                    @if($canMoney)<th class="px-3 py-3 text-right font-medium"><a href="{{ $sortLink('total') }}">Total {{ $arrow('total') }}</a></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse($orders as $order)
                    @php
                        $dt = $order->created_at;
                        $recent = $dt->diffInHours(now(), true) < 24 * 7;
                    @endphp
                    <tr class="cursor-pointer hover:bg-stone-50 {{ $order->is_late ? 'bg-red-50/40' : '' }}" data-href="{{ route('admin.orders.show', $order->id) }}">
                        <td class="px-4 py-3"><input type="checkbox" name="ids[]" value="{{ $order->id }}" class="row-check rounded border-stone-300" aria-label="Pilih {{ $order->order_number }}"></td>
                        <td class="px-3 py-3 font-medium text-stone-900">
                            <a href="{{ route('admin.orders.show', $order->id) }}" class="hover:underline">{{ $order->order_number }}</a>
                            <div class="text-xs font-normal text-stone-400">{{ $order->items->sum('quantity') }} item</div>
                        </td>
                        <td class="px-3 py-3"><div>{{ $order->customer->name ?? 'Guest' }}</div><div class="text-xs text-stone-500">{{ $order->customer->phone ?? '-' }}</div></td>
                        <td class="px-3 py-3">
                            <span class="text-xs px-2.5 py-1 rounded-md font-medium {{ $pill[$order->status] ?? 'bg-stone-100 text-stone-700' }}">{{ $order->status_label }}</span>
                            @if($order->is_late)<span class="ml-1 text-xs text-red-600 font-medium">Terlambat {{ $order->late_hours }} jam</span>@endif
                            <div class="mt-1 text-xs text-stone-500">
                                {{ $order->payment ? $order->payment->statusLabel() . ' · ' . $order->payment->methodLabel() : 'Belum bayar' }}
                                @if($order->shipment?->courier)
                                    <span class="text-stone-300">|</span> {{ strtoupper($order->shipment->courier) }}@if($order->shipment->tracking_number) <span class="font-mono">{{ $order->shipment->tracking_number }}</span>@endif
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-3 text-xs text-stone-600 whitespace-nowrap" title="{{ wib($dt, 'd M Y H:i', true) }}">
                            {{ $recent ? $dt->copy()->locale('id')->diffForHumans() : wib($dt, 'd M Y') }}
                        </td>
                        @if($canMoney)<td class="px-3 py-3 text-right font-semibold whitespace-nowrap">Rp {{ number_format($order->total, 0, ',', '.') }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-14 text-center text-stone-500">
                        @if($current === 'perlu' && ! $chips)
                            Tidak ada pesanan yang perlu ditindaklanjuti.
                            <a href="{{ route('admin.orders.index', ['status' => 'all']) }}" class="block mt-2 text-sm text-stone-800 underline underline-offset-2">Lihat semua pesanan</a>
                        @else
                            Tidak ada pesanan yang cocok dengan filter.
                        @endif
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
    const rows = () => Array.from(document.querySelectorAll('.row-check'));
    function sync() {
        const n = rows().filter(c => c.checked).length;
        cnt.textContent = n; bar.classList.toggle('hidden', n === 0); mode();
        all.checked = n > 0 && n === rows().length;
    }
    // Seluruh baris bisa diklik; klik pada link, checkbox, atau tombol tidak ikut memicu.
    document.querySelectorAll('tr[data-href]').forEach(tr => tr.addEventListener('click', e => {
        if (e.target.closest('a, input, button, label, select') || window.getSelection().toString()) return;
        window.location = tr.dataset.href;
    }));
    const act = document.querySelector('#bulkBar select[name=action]'), reason = document.getElementById('bulkReason');
    const form = document.getElementById('bulkForm');
    // Aksi ringan: tunda 5 detik dengan tombol Urungkan. Aksi berisiko (kirim, batalkan): dialog konfirmasi.
    function mode() {
        const n = rows().filter(c => c.checked).length, a = act.value;
        reason.hidden = a !== 'cancel';
        ['undo', 'confirmTitle', 'confirmText', 'confirmOk', 'confirmTone'].forEach(k => delete form.dataset[k]);
        if (a === 'process') form.dataset.undo = n + ' pesanan ditandai diproses…';
        else if (a === 'complete') form.dataset.undo = n + ' pesanan ditandai selesai…';
        else if (a === 'ship') Object.assign(form.dataset, { confirmTitle: 'Kirim ' + n + ' pesanan?', confirmText: 'Pelanggan akan diberi tahu. Pesanan yang resinya belum diisi dilewati.', confirmOk: 'Ya, kirim' });
        else if (a === 'cancel') Object.assign(form.dataset, { confirmTitle: 'Batalkan ' + n + ' pesanan?', confirmText: 'Stok dikembalikan bila sudah terpotong. Aksi ini tidak bisa diurungkan.', confirmOk: 'Ya, batalkan', confirmTone: 'danger' });
    }
    act.addEventListener('change', mode);
    document.getElementById('bulkPrint').addEventListener('click', (e) => {
        // submit() programatik tidak memicu event submit, jadi lolos dari kunci/konfirmasi.
        const oldAction = form.action, oldTarget = form.target, oldAct = act.disabled;
        act.disabled = true; form.action = e.currentTarget.dataset.url; form.target = '_blank';
        HTMLFormElement.prototype.submit.call(form);
        form.action = oldAction; form.target = oldTarget; act.disabled = oldAct;
    });
    document.getElementById('bulkClear').addEventListener('click', () => { rows().forEach(c => c.checked = false); sync(); });
    all.addEventListener('change', () => { rows().forEach(c => c.checked = all.checked); sync(); });
    rows().forEach(c => c.addEventListener('change', sync));
})();
</script>
@endpush
