@extends('layouts.admin')

@section('title', 'Input Resi Cepat')

@section('content')
@php $canShip = auth()->user()->can('ship_orders'); @endphp

<div class="mb-5">
    <a href="{{ route('admin.shipments.index') }}" class="text-sm text-stone-500 hover:text-stone-700">Kembali ke Pengiriman</a>
    <h1 class="mt-2 text-2xl font-bold text-stone-900">Input resi cepat</h1>
    <p class="mt-1 text-sm text-stone-500">Scan atau ketik resi, lalu tekan <kbd class="rounded border border-stone-300 bg-white px-1.5 text-xs">Enter</kbd>. Resi tersimpan dan fokus pindah ke baris berikutnya.</p>
</div>

@if($waiting > 0)
    <p class="mb-4 rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm text-stone-600">
        {{ $waiting }} pesanan lain masih menunggu dikemas.
        <a href="{{ route('admin.orders.index', ['status' => 'dikonfirmasi']) }}" class="font-medium text-stone-900 underline underline-offset-2">Lihat pesanannya</a>
    </p>
@endif

<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-xs text-stone-500">
            <tr>
                <th class="px-4 py-3 font-medium">Pesanan</th>
                <th class="px-4 py-3 font-medium">Penerima</th>
                <th class="px-4 py-3 font-medium">Pilihan pelanggan</th>
                <th class="px-4 py-3 font-medium">Kurir</th>
                <th class="px-4 py-3 font-medium">No. resi</th>
                <th class="px-4 py-3 font-medium">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100" id="quickRows">
        @forelse($orders as $o)
            @php
                $draft = $o->shipment && $o->shipment->status === 'pending' ? $o->shipment : null;
                $saved = $draft && $draft->tracking_number;
                $guess = $draft?->courier ?: \App\Support\Couriers::guess($o->shipping_method);
            @endphp
            <tr data-id="{{ $o->id }}" data-saved="{{ $saved ? '1' : '0' }}" class="{{ $saved ? 'bg-stone-50' : '' }}">
                <td class="px-4 py-3">
                    <a href="{{ route('admin.orders.show', $o->id) }}" class="font-mono font-medium text-stone-900 hover:underline">{{ $o->order_number }}</a>
                    <div class="text-xs text-stone-500">{{ $o->items->sum('quantity') }} unit</div>
                </td>
                <td class="px-4 py-3">
                    <div>{{ $o->shipping_name ?: ($o->customer->name ?? '-') }}</div>
                    <div class="max-w-xs truncate text-xs text-stone-500" title="{{ $o->full_shipping_address }}">{{ $o->full_shipping_address ?: ($o->customer->address ?? '-') }}</div>
                </td>
                <td class="px-4 py-3 text-xs text-stone-600">{{ $o->shipping_method ?: '-' }}@if($o->shipping_etd)<div class="text-stone-400">estimasi {{ $o->shipping_etd }}</div>@endif</td>
                <td class="px-4 py-3">
                    <select class="w-36 rounded-lg border border-stone-300 px-2 py-1.5 text-sm" aria-label="Kurir {{ $o->order_number }}">
                        <option value="">Pilih…</option>
                        @foreach(\App\Support\Couriers::options() as $name)
                            <option value="{{ $name }}" @selected($guess === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </td>
                <td class="px-4 py-3">
                    <input type="text" data-resi value="{{ $draft?->tracking_number }}" maxlength="60" autocomplete="off" spellcheck="false"
                           placeholder="Scan / ketik resi" aria-label="Resi {{ $o->order_number }}"
                           class="w-52 rounded-lg border border-stone-300 px-3 py-1.5 font-mono text-sm">
                </td>
                <td class="px-4 py-3 text-xs" data-status>
                    <span class="{{ $saved ? 'text-stone-600' : 'text-stone-400' }}">{{ $saved ? 'Tersimpan' : 'Belum diisi' }}</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-stone-500">Tidak ada pesanan yang menunggu resi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($orders->isNotEmpty())
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-500"><b id="quickDone" class="text-stone-900">0</b> dari {{ $orders->count() }} resi tersimpan</p>
        @if($canShip)
            <form method="POST" action="{{ route('admin.orders.bulk') }}" id="quickShip">
                @csrf
                <input type="hidden" name="action" value="ship">
                <button type="submit" class="rounded-lg bg-stone-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-stone-800">Kirim yang sudah ada resi</button>
            </form>
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const url = @js(route('admin.shipments.quick-save', '__ID__'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const rows = () => Array.from(document.querySelectorAll('#quickRows tr[data-id]'));
    const done = document.getElementById('quickDone');
    const count = () => { if (done) done.textContent = rows().filter(r => r.dataset.saved === '1').length; };
    const say = (row, text, cls) => { row.querySelector('[data-status]').innerHTML = '<span class="' + cls + '"></span>'; row.querySelector('[data-status] span').textContent = text; };
    const focusNext = (row) => {
        const list = rows(), i = list.indexOf(row);
        const next = list.slice(i + 1).concat(list.slice(0, i)).find(r => r.dataset.saved !== '1');
        if (next) next.querySelector('[data-resi]').focus();
        else document.querySelector('#quickShip button')?.focus();
    };

    rows().forEach(row => {
        const input = row.querySelector('[data-resi]'), select = row.querySelector('select');
        // Resi diubah setelah tersimpan: tandai perlu disimpan lagi.
        input.addEventListener('input', () => { if (row.dataset.saved === '1') { row.dataset.saved = '0'; row.classList.remove('bg-stone-50'); say(row, 'Belum disimpan', 'text-stone-400'); count(); } });
        input.addEventListener('keydown', async (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const resi = input.value.trim();
            if (!select.value) { say(row, 'Pilih kurir dulu.', 'text-red-700'); select.focus(); return; }
            if (!resi) { focusNext(row); return; }
            input.disabled = true; say(row, 'Menyimpan…', 'text-stone-500');
            try {
                const res = await fetch(url.replace('__ID__', row.dataset.id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ courier: select.value, tracking_number: resi }),
                });
                const d = await res.json().catch(() => ({}));
                input.disabled = false;
                if (res.ok) {
                    input.value = d.resi; row.dataset.saved = '1'; row.classList.add('bg-stone-50');
                    say(row, 'Tersimpan', 'text-stone-600'); count(); focusNext(row);
                } else {
                    const msg = d.message || (d.errors && Object.values(d.errors)[0][0]) || 'Gagal menyimpan.';
                    say(row, msg, 'text-red-700'); input.focus(); input.select();
                }
            } catch (err) { input.disabled = false; say(row, 'Koneksi bermasalah. Coba lagi.', 'text-red-700'); input.focus(); }
        });
    });

    const form = document.getElementById('quickShip');
    if (form) form.addEventListener('submit', (e) => {
        const ids = rows().filter(r => r.dataset.saved === '1').map(r => r.dataset.id);
        if (!ids.length) { e.preventDefault(); alert('Belum ada resi yang tersimpan.'); return; }
        if (!confirm('Kirim ' + ids.length + ' pesanan? Pelanggan akan diberi tahu.')) { e.preventDefault(); return; }
        ids.forEach(id => { const h = document.createElement('input'); h.type = 'hidden'; h.name = 'ids[]'; h.value = id; form.appendChild(h); });
    });

    count();
    const first = rows().find(r => r.dataset.saved !== '1');
    if (first) first.querySelector('[data-resi]').focus();
})();
</script>
@endpush
