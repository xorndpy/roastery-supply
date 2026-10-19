@extends('layouts.admin')

@section('title', 'Pembayaran')

@section('content')
@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $statusColor = ['waiting' => '#d9a97a', 'pending' => '#c9902e', 'verified' => '#5b7f4b', 'rejected' => '#a8998b', 'expired' => '#a8998b', 'review' => '#b3402f', 'duplicate' => '#b3402f', 'refunded' => '#7a6a5d'];
    $tabs = ['perhatian' => 'Perlu perhatian', 'menunggu' => 'Menunggu pelanggan', 'otomatis' => 'Otomatis', 'manual' => 'Transfer manual', 'semua' => 'Semua'];
    $lede = $counts['perhatian'] > 0
        ? $counts['perhatian'] . ' pembayaran perlu perhatian Anda. Sisanya diproses otomatis.'
        : 'Tidak ada yang perlu ditindak. ' . $autoThisWeek . ' pembayaran dikonfirmasi otomatis dalam 7 hari terakhir.';
@endphp

<div class="dash">
    <header class="dash-head">
        <div>
            <p class="dash-eyebrow">Keuangan</p>
            <h1 class="dash-h1">Pembayaran</h1>
            <p class="dash-lede">{{ $lede }}</p>
        </div>
    </header>

    <nav class="pay-tabs" aria-label="Kelompok pembayaran">
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.payments.index', ['tab' => $key]) }}" class="pay-tab {{ $key === 'perhatian' && $counts['perhatian'] > 0 ? 'is-alert' : '' }}" @if($tab === $key) aria-current="true" @endif>
                {{ $label }} <b>{{ $counts[$key] }}</b>
            </a>
        @endforeach
    </nav>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="block text-xs text-stone-600">Cari pesanan / pelanggan
            <input type="text" name="search" value="{{ request('search') }}" placeholder="ORD-… atau nama" class="mt-1 block w-56 rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </label>
        <label class="block text-xs text-stone-600">Dari
            <input type="date" name="from" value="{{ request('from') }}" class="mt-1 block rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </label>
        <label class="block text-xs text-stone-600">Sampai
            <input type="date" name="to" value="{{ request('to') }}" class="mt-1 block rounded-lg border border-stone-300 px-3 py-2 text-sm">
        </label>
        <button class="pay-btn is-primary">Terapkan</button>
        @if(request()->hasAny(['search', 'from', 'to']))
            <a href="{{ route('admin.payments.index', ['tab' => $tab]) }}" class="pay-btn is-quiet">Reset</a>
        @endif
    </form>

    <section class="dash-card" aria-label="Daftar pembayaran">
        <div class="dash-tablewrap overflow-x-auto" style="margin-top:-.25rem; border-top:0">
            <table class="dash-table">
                <thead>
                    <tr><th>Pesanan</th><th>Metode</th><th class="text-right">Jumlah</th><th>Status</th><th class="text-right">Waktu</th><th class="text-right">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>
                                <a href="{{ route('admin.payments.show', $payment->id) }}" class="dash-order-no">{{ $payment->order->order_number }}</a>
                                <div class="text-xs text-stone-500">{{ $payment->order->customer->name ?? 'Guest' }}</div>
                            </td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    @if($payment->proof_image)
                                        <a href="{{ route('admin.payments.proof', $payment->id) }}" target="_blank" rel="noopener"><img src="{{ route('admin.payments.proof', $payment->id) }}" alt="Bukti transfer" class="h-9 w-9 rounded border border-stone-200 object-cover"></a>
                                    @endif
                                    <div>
                                        <div>{{ $payment->methodLabel() }}</div>
                                        <div class="text-xs text-stone-500">{{ $payment->isGateway() ? 'Otomatis' : 'Manual' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right font-medium tabular-nums">{{ $rp($payment->amount) }}</td>
                            <td>
                                <span class="dash-badge" style="--c: {{ $statusColor[$payment->status] ?? '#a8998b' }}"><i></i>{{ $payment->statusLabel() }}</span>
                                @if($payment->review_reason && !$payment->reviewed_at && $payment->reasonTitle() !== $payment->statusLabel())
                                    <span class="pay-reason">{{ $payment->reasonTitle() }}</span>
                                @endif
                            </td>
                            <td class="text-right text-xs text-stone-500" title="{{ $payment->created_at->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i') }} WIB">{{ $payment->created_at->locale('id')->diffForHumans() }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($payment->status === 'pending')
                                        @can('verify_payments')
                                            <form method="POST" action="{{ route('admin.payments.verify', $payment->id) }}" class="inline" onsubmit="return confirm('Nominal sudah masuk ke rekening? Verifikasi akan mengurangi stok.')">
                                                @csrf
                                                <button class="pay-btn is-primary !py-1.5">Verifikasi</button>
                                            </form>
                                        @endcan
                                    @endif
                                    <a href="{{ route('admin.payments.show', $payment->id) }}" class="pay-btn is-quiet !py-1.5">{{ $payment->requiresAttention() ? 'Tinjau' : 'Detail' }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-14 text-center text-stone-500">
                            @if($tab === 'perhatian')
                                <span class="dash-clear-ico" style="display:inline-grid"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
                                <p class="dash-clear-title">Semua beres</p>
                                <p class="dash-clear-text">Tidak ada pembayaran yang menunggu tindakan Anda.</p>
                            @else
                                Tidak ada pembayaran pada kelompok ini.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-5">{{ $payments->links() }}</div>
</div>
@endsection
