@extends('layouts.admin')

@section('title', 'Pembayaran ' . $payment->order->order_number)

@section('content')
@php
    $order = $payment->order;
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $statusColor = ['waiting' => '#d9a97a', 'pending' => '#c9902e', 'verified' => '#5b7f4b', 'rejected' => '#a8998b', 'expired' => '#a8998b', 'review' => '#b3402f', 'duplicate' => '#b3402f', 'refunded' => '#7a6a5d'];
    $flagged = $payment->review_reason && !$payment->reviewed_at;
    $canApprove = $payment->status === 'review' && in_array($payment->review_reason, ['stok_kurang', 'nominal_tidak_cocok'], true);
    $canResolve = $flagged;     // refund / selesai ditinjau
    $meta = $payment->gateway_meta ?? [];
    $wib = fn ($d) => $d ? $d->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i:s') . ' WIB' : '-';
@endphp

<div class="dash">
    <a href="{{ route('admin.payments.index') }}" class="text-sm text-stone-500 hover:text-stone-800">← Kembali ke Pembayaran</a>
    <header class="dash-head" style="margin-top:.75rem">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="dash-h1" style="margin-top:0"><span class="font-mono text-[1.6rem]">{{ $payment->isGateway() ? $payment->provider_order_id : $order->order_number }}</span></h1>
            <span class="dash-badge" style="--c: {{ $statusColor[$payment->status] ?? '#a8998b' }}"><i></i>{{ $payment->statusLabel() }}</span>
        </div>
    </header>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">

            {{-- ============ Perlu tindakan ============ --}}
            @if($flagged)
                <section class="pay-flag" aria-labelledby="flag-title">
                    <svg class="mt-0.5 h-6 w-6 shrink-0 text-red-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.8l9 15.7H3L12 3.8z"/><path d="M12 10v4M12 17v.1"/></svg>
                    <div class="min-w-0 flex-1">
                        <h2 id="flag-title">{{ $payment->reasonTitle() }}</h2>
                        <p>{{ $payment->reasonHelp() }}</p>
                        @if($payment->note)<p class="mt-2 rounded-md bg-white/70 px-3 py-2 text-[13px]">{{ $payment->note }}</p>@endif

                        @can('verify_payments')
                            <div class="mt-4 flex flex-wrap items-start gap-3">
                                @if($canApprove)
                                    <form method="POST" action="{{ route('admin.payments.approve', $payment->id) }}">@csrf
                                        <button class="pay-btn is-primary">Setujui pembayaran</button>
                                    </form>
                                @endif
                                @if($payment->isGateway())
                                    <form method="POST" action="{{ route('admin.payments.sync', $payment->id) }}">@csrf
                                        <button class="pay-btn is-quiet">Sinkronkan dari Midtrans</button>
                                    </form>
                                @endif
                            </div>

                            @if($canResolve)
                                <form method="POST" action="{{ route('admin.payments.resolve', $payment->id) }}" class="mt-4 space-y-3 border-t border-red-100 pt-4">
                                    @csrf
                                    <label class="block text-xs text-stone-600">Catatan (opsional)
                                        <input type="text" name="note" maxlength="500" class="mt-1 block w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm" placeholder="mis. refund dibuat di dashboard Midtrans pada …">
                                    </label>
                                    @if($order->status === 'menunggu_bayar' && in_array($payment->status, ['review', 'duplicate'], true))
                                        <label class="flex items-center gap-2 text-sm text-stone-700"><input type="checkbox" name="cancel_order" value="1" class="rounded border-stone-400"> Batalkan pesanan ini juga</label>
                                    @endif
                                    <div class="flex flex-wrap gap-3">
                                        @if(in_array($payment->status, ['review', 'duplicate'], true))
                                            <button name="action" value="refunded" class="pay-btn is-quiet">Tandai sudah direfund</button>
                                        @endif
                                        <button name="action" value="dismiss" class="pay-btn is-quiet">Tandai selesai ditinjau</button>
                                    </div>
                                </form>
                            @endif
                        @endcan
                    </div>
                </section>
            @endif

            {{-- ============ Bukti manual + verifikasi (alur lama) ============ --}}
            @if(!$payment->isGateway())
                <section class="dash-card">
                    <div class="dash-card-head"><h2>Bukti pembayaran</h2></div>
                    @if($payment->proof_image)
                        <a href="{{ route('admin.payments.proof', $payment->id) }}" target="_blank" rel="noopener"><img src="{{ route('admin.payments.proof', $payment->id) }}" alt="Bukti transfer" class="max-h-[32rem] rounded border border-stone-200"></a>
                    @else
                        <p class="text-sm text-stone-500">Tidak ada file bukti.</p>
                    @endif
                </section>

                @if($payment->status === 'pending')
                    @canany(['verify_payments', 'reject_payments'])
                        <section class="dash-card">
                            <div class="dash-card-head"><h2>Verifikasi</h2></div>
                            <p class="mb-4 text-sm text-stone-600">Pastikan nominal <strong>{{ $rp($payment->amount) }}</strong> sudah masuk ke rekening. Verifikasi akan mengurangi stok.</p>
                            <div class="flex flex-wrap items-start gap-6">
                                @can('verify_payments')
                                    <form method="POST" action="{{ route('admin.payments.verify', $payment->id) }}">@csrf
                                        <button class="pay-btn is-primary !px-6 !py-2.5">Verifikasi Pembayaran</button>
                                    </form>
                                @endcan
                                @can('reject_payments')
                                    <form method="POST" action="{{ route('admin.payments.reject', $payment->id) }}" class="min-w-[260px] flex-1 space-y-2">@csrf
                                        <textarea name="note" rows="2" maxlength="500" placeholder="Alasan penolakan (dilihat customer)" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"></textarea>
                                        <button class="pay-btn is-quiet !border-red-300 !text-red-700">Tolak</button>
                                    </form>
                                @endcan
                            </div>
                        </section>
                    @endcanany
                @endif
            @else
                {{-- ============ Rincian gateway ============ --}}
                <section class="dash-card">
                    <div class="dash-card-head">
                        <h2>Rincian gateway</h2>
                        @can('verify_payments')
                            @if(in_array($payment->status, ['waiting', 'review'], true))
                                <form method="POST" action="{{ route('admin.payments.sync', $payment->id) }}">@csrf<button class="pay-btn is-quiet !py-1.5">Sinkronkan</button></form>
                            @endif
                        @endcan
                    </div>
                    <dl class="pay-kv">
                        <dt>Penyedia</dt><dd>Midtrans</dd>
                        <dt>ID transaksi (order_id)</dt><dd class="font-mono text-[12.5px]">{{ $payment->provider_order_id }}</dd>
                        <dt>ID transaksi Midtrans</dt><dd class="font-mono text-[12.5px]">{{ $payment->provider_transaction_id ?: '-' }}</dd>
                        <dt>Metode dipilih</dt><dd>{{ $payment->methodLabel() }}</dd>
                        <dt>Status di gateway</dt><dd>{{ $payment->gateway_status ?: '-' }}</dd>
                        @if(!empty($meta['va_numbers']))
                            <dt>Nomor VA</dt><dd class="font-mono text-[12.5px]">@foreach($meta['va_numbers'] as $va){{ strtoupper($va['bank'] ?? '') }} {{ $va['va_number'] ?? '' }}@if(!$loop->last), @endif @endforeach</dd>
                        @endif
                        <dt>Batas waktu bayar</dt><dd>{{ $wib($payment->expires_at) }}</dd>
                        <dt>Dibayar pada</dt><dd>{{ $wib($payment->paid_at) }}</dd>
                        <dt>Terakhir dicek</dt><dd>{{ $wib($payment->last_checked_at) }}</dd>
                    </dl>
                </section>

                {{-- ============ Riwayat notifikasi ============ --}}
                <section class="dash-card" aria-labelledby="events-title">
                    <div class="dash-card-head">
                        <div>
                            <h2 id="events-title">Riwayat notifikasi</h2>
                            <p class="dash-sub">Setiap pesan dari gateway dicatat sebelum diproses. Pesan yang sama tidak diproses dua kali.</p>
                        </div>
                    </div>
                    <div class="pay-timeline overflow-x-auto">
                        <table>
                            <thead class="text-left text-xs uppercase tracking-wide text-stone-500"><tr><th>Waktu</th><th>Sumber</th><th>Status</th><th>Hasil</th></tr></thead>
                            <tbody>
                                @forelse($events as $e)
                                    <tr>
                                        <td class="whitespace-nowrap text-xs text-stone-600">{{ $wib($e->created_at) }}</td>
                                        <td><span class="pay-src">{{ $e->source }}</span></td>
                                        <td>{{ $e->transaction_status ?? '-' }}@if($e->fraud_status && $e->fraud_status !== 'accept') <span class="text-xs text-stone-500">({{ $e->fraud_status }})</span>@endif</td>
                                        <td>{{ $e->outcomeLabel() }}@if($e->outcome_note)<div class="text-xs text-stone-500">{{ $e->outcome_note }}</div>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-8 text-center text-stone-500">Belum ada notifikasi dari gateway.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        {{-- ============ Kanan: ringkasan ============ --}}
        <div class="space-y-5">
            <section class="dash-card">
                <div class="dash-card-head"><h2>Pembayaran</h2></div>
                <dl class="pay-kv" style="grid-template-columns: 6.5rem 1fr">
                    <dt>Metode</dt><dd>{{ $payment->methodLabel() }}</dd>
                    <dt>Jumlah</dt><dd class="font-semibold">{{ $rp($payment->amount) }}</dd>
                    <dt>Dibuat</dt><dd>{{ $wib($payment->created_at) }}</dd>
                    @if($payment->verified_at)
                        <dt>Dikonfirmasi</dt><dd>{{ $wib($payment->verified_at) }}</dd>
                        <dt>Oleh</dt><dd>{{ $payment->verifier->name ?? ($payment->isGateway() ? 'Otomatis (gateway)' : '-') }}</dd>
                    @endif
                    @if($payment->note && !$flagged)
                        <dt>Catatan</dt><dd class="whitespace-pre-line">{{ $payment->note }}</dd>
                    @endif
                </dl>
            </section>

            <section class="dash-card">
                <div class="dash-card-head"><h2>Pesanan</h2></div>
                <dl class="pay-kv" style="grid-template-columns: 6.5rem 1fr">
                    <dt>Status</dt><dd class="capitalize">{{ $order->status_label ?? $order->status }}</dd>
                    <dt>Total</dt><dd class="font-semibold">{{ $rp($order->total) }}</dd>
                </dl>
                @if((float) $payment->amount !== (float) $order->total)
                    <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">Nominal pembayaran berbeda dengan total pesanan.</div>
                @endif
                <div class="mt-3 border-t border-stone-100 pt-3 text-sm">
                    <div class="font-medium">{{ $order->shipping_name ?: ($order->customer->name ?? '-') }}</div>
                    <div class="text-stone-500">{{ $order->shipping_phone ?: ($order->customer->phone ?? '') }}</div>
                    <div class="text-stone-500">{{ $order->full_shipping_address ?: ($order->customer->address ?? '-') }}</div>
                </div>
                <ul class="mt-3 space-y-1 border-t border-stone-100 pt-3 text-sm">
                    @foreach($order->items as $item)
                        <li class="flex justify-between gap-3"><span>{{ $item->product_name }} × {{ $item->quantity }}</span><span class="tabular-nums">{{ $rp($item->subtotal) }}</span></li>
                    @endforeach
                </ul>
                @can('view_orders')
                    <a href="{{ route('admin.orders.show', $order->id) }}" class="dash-link mt-3 inline-block">Buka pesanan →</a>
                @endcan
            </section>
        </div>
    </div>
</div>
@endsection
