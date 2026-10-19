@extends('layouts.app')

@section('title', 'Detail Pesanan ' . $order->order_number)

@section('content')
<div class="border-b border-stone-200 bg-stone-100">
    <div class="container-x py-5 text-[13px] text-stone-500">
        <a href="{{ route('home') }}" class="hover:text-stone-900">Beranda</a> <span class="mx-1">/</span>
        <a href="{{ route('customer.orders.index') }}" class="hover:text-stone-900">Pesanan Saya</a> <span class="mx-1">/</span>
        <span class="text-stone-900">{{ $order->order_number }}</span>
    </div>
</div>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
        <div class="flex flex-wrap justify-between items-start gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ $order->order_number }}</h1>
                <div class="text-sm text-stone-500 mt-1">
                    {{ $order->created_at->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') . ' WIB' }}
                </div>
            </div>
            <div class="text-right">
                @php
                    $statusColors = [
                        'menunggu_bayar' => 'bg-yellow-100 text-yellow-800',
                        'dikonfirmasi' => 'bg-orange-100 text-orange-800',
                        'diproses' => 'bg-orange-200 text-orange-900',
                        'dikirim' => 'bg-stone-200 text-stone-800',
                        'selesai' => 'bg-green-100 text-green-800',
                        'batal' => 'bg-red-100 text-red-800',
                    ];
                    $statusLabels = [
                        'menunggu_bayar' => 'Menunggu Bayar',
                        'dikonfirmasi' => 'Dikonfirmasi',
                        'diproses' => 'Diproses',
                        'dikirim' => 'Dikirim',
                        'selesai' => 'Selesai',
                        'batal' => 'Batal',
                    ];
                @endphp
                <span class="text-sm px-4 py-2 rounded-full font-medium {{ $statusColors[$order->status] ?? 'bg-stone-100' }}">
                    {{ $statusLabels[$order->status] ?? $order->status }}
                </span>
            </div>
        </div>
    </div>

    {{-- Detail Item --}}
    <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
        <h2 class="font-bold mb-4">Item Pesanan</h2>
        <div class="space-y-3">
            @foreach($order->items as $item)
                <div class="flex justify-between text-sm">
                    <div>
                        <div class="font-medium">{{ $item->product_name }}</div>
                        <div class="text-stone-500">Rp {{ number_format($item->price, 0, ',', '.') }} × {{ $item->quantity }}</div>
                    </div>
                    <div class="font-medium">
                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-t border-stone-200 mt-4 pt-4 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-stone-600">Subtotal</span>
                <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($order->discount > 0)
                <div class="flex justify-between text-green-600">
                    <span>Diskon{{ $order->voucher_code ? ' (' . $order->voucher_code . ')' : '' }}</span>
                    <span>- Rp {{ number_format($order->discount, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between">
                <span class="text-stone-600">Ongkir{{ $order->shipping_method ? ' (' . $order->shipping_method . ($order->shipping_etd ? ', ' . $order->shipping_etd : '') . ')' : '' }}</span>
                <span>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between font-bold text-lg pt-2 border-t">
                <span>Total</span>
                <span class="text-orange-500">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Pembayaran --}}
    @if($order->status === 'menunggu_bayar')
        @php
            // Bukti manual dan percobaan online dipisah: "terbaru" bisa saja sesi online yang belum dibayar.
            $allPayments = $order->payments()->orderByDesc('id')->get();
            $payment = $allPayments->whereNull('provider')->first();            // bukti manual terbaru
            $isPending = $payment && $payment->status === 'pending';
            $isRejected = !$isPending && $payment && $payment->status === 'rejected';
            $online = $allPayments->whereNotNull('provider')->first();           // sesi online terbaru
            $reviewing = $allPayments->first(fn ($p) => $p->status === 'review' && !$p->reviewed_at);
            $midtrans = app(\App\Services\Payments\MidtransService::class);
            $onlineEnabled = $midtrans->configured() && !$isPending && !$reviewing;
            $hasLive = $online && $online->status === 'waiting' && $online->snap_token && $online->expires_at && $online->expires_at->isFuture();
        @endphp
        <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
            <h2 class="font-bold mb-1">Pembayaran</h2>
            <p class="text-sm text-stone-500 mb-4">
                Bayar sebelum <strong>{{ $order->payment_deadline->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') . ' WIB' }}</strong>.
                Pesanan yang belum dibayar akan dibatalkan otomatis.
            </p>

            {{-- Status pembayaran real-time (diumumkan ke pembaca layar, tidak merebut fokus) --}}
            <div id="payLive" role="status" aria-live="polite" class="hidden mb-4 rounded-lg border p-4 text-sm"></div>

            @if($reviewing)
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <div class="font-medium">Pembayaran Anda sedang kami tinjau</div>
                    <p class="mt-1">Dana sudah kami terima dan tim kami sedang memprosesnya. Anda tidak perlu membayar lagi; kami akan menghubungi Anda bila diperlukan.</p>
                </div>
            @endif

            @if($onlineEnabled)
                <div class="mb-6 rounded-lg border border-stone-200 p-5" id="onlineBox">
                    <div class="font-medium">Bayar online</div>
                    <p class="mt-1 text-sm text-stone-600">Virtual Account, QRIS, e-wallet, atau kartu. Pembayaran terkonfirmasi otomatis dalam hitungan detik, tanpa perlu mengunggah bukti.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <button type="button" id="payBtn" data-start="{{ route('customer.orders.pay-online', $order->order_number) }}"
                                class="rounded-lg bg-orange-500 px-6 py-3 font-medium text-white transition hover:bg-orange-600 disabled:cursor-wait disabled:opacity-70">
                            {{ $hasLive ? 'Lanjutkan pembayaran' : 'Bayar Sekarang' }}
                        </button>
                        <button type="button" id="checkBtn" class="text-sm text-stone-600 underline hover:text-stone-900">Cek status pembayaran</button>
                    </div>
                    <p class="mt-3 text-xs text-stone-500">Sudah membayar? Status terbarui otomatis. Jangan bayar dua kali; bila ragu, tekan “Cek status pembayaran”.</p>
                </div>
            @endif

            @if($isPending)
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg mb-4">
                    <div class="font-medium text-yellow-800">Bukti pembayaran sudah diunggah</div>
                    <div class="text-sm text-yellow-700 mt-1">Menunggu verifikasi admin. Salah unggah? Kirim ulang di bawah untuk mengganti.</div>
                    @if($payment->proof_image)
                        <img src="{{ route('customer.orders.proof', $order->order_number) }}"
                             alt="Bukti" class="mt-3 max-w-xs rounded border">
                    @endif
                </div>
            @endif

            @if($isRejected)
                <div class="bg-red-50 border border-red-200 p-4 rounded-lg mb-4">
                    <div class="font-medium text-red-800">Bukti pembayaran ditolak</div>
                    <div class="text-sm text-red-700 mt-1">{{ $payment->note ?: 'Silakan unggah ulang bukti transfer yang valid.' }}</div>
                </div>
            @endif

            <details class="group" @if(!$onlineEnabled || $isPending || $isRejected) open @endif>
                @if($onlineEnabled)<summary class="mb-4 cursor-pointer text-sm font-medium text-stone-700 hover:text-stone-900">Atau bayar dengan transfer manual (unggah bukti)</summary>@endif
            @unless($isPending)
                <div class="bg-orange-50 border border-orange-200 p-4 rounded-lg mb-6">
                    <div class="font-medium text-orange-900">Instruksi Pembayaran</div>
                    <p class="text-sm text-orange-700 mt-1">
                        Transfer ke rekening berikut, lalu upload bukti transfer:
                    </p>
                    @if($site['bank_name'] && $site['bank_account_number'])
                        <div class="mt-2 font-mono text-sm bg-white p-3 rounded border border-orange-200">
                            <div>{{ $site['bank_name'] }}</div>
                            <div>No. Rek: <strong>{{ $site['bank_account_number'] }}</strong></div>
                            @if($site['bank_account_name'])<div>a/n: {{ $site['bank_account_name'] }}</div>@endif
                        </div>
                    @else
                        <div class="mt-2 text-sm bg-white p-3 rounded border border-orange-200 text-stone-600">
                            Nomor rekening belum diatur. Silakan <a href="{{ route('contact.index') }}" class="underline">hubungi kami</a> untuk detail pembayaran.
                        </div>
                    @endif
                    @if($site['payment_note'])<p class="mt-2 text-sm text-orange-700 whitespace-pre-line">{{ $site['payment_note'] }}</p>@endif
                    <div class="mt-3 text-sm text-orange-700">
                        Jumlah: <strong>Rp {{ number_format($order->total, 0, ',', '.') }}</strong>
                    </div>
                </div>
            @endunless

            <form method="POST" action="{{ route('customer.orders.pay', $order->order_number) }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium mb-2">Metode Pembayaran *</label>
                    <select name="method" required
                            class="w-full border border-stone-300 rounded-lg px-3 py-2">
                        <option value="transfer_bca">Transfer BCA</option>
                        <option value="transfer_mandiri">Transfer Mandiri</option>
                        <option value="transfer_bni">Transfer BNI</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Bukti Transfer * <span class="text-stone-400 font-normal">(JPG/PNG/WEBP, maks 2 MB)</span></label>
                    <input type="file" name="proof_image" accept="image/jpeg,image/png,image/webp" required
                           class="w-full border border-stone-300 rounded-lg px-3 py-2">
                    @error('proof_image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Catatan (opsional)</label>
                    <textarea name="note" rows="2"
                              class="w-full border border-stone-300 rounded-lg px-3 py-2"></textarea>
                </div>

                <button type="submit"
                        class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
                    {{ $isPending ? 'Ganti Bukti Pembayaran' : 'Upload Bukti Pembayaran' }}
                </button>
            </form>

            </details>

            <form method="POST" action="{{ route('customer.orders.cancel', $order->order_number) }}" class="mt-4">
                @csrf
                <button type="submit" onclick="return confirm('Yakin batalkan pesanan ini?')"
                        class="text-sm text-red-600 hover:underline">
                    Batalkan pesanan
                </button>
            </form>
        </div>
    @endif

    {{-- Info Pengiriman --}}
    @if($order->shipment && $order->shipment->tracking_number)
        <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
            <h2 class="font-bold mb-4">Info Pengiriman</h2>
            <div class="text-sm space-y-2">
                <div><span class="text-stone-500">Kurir:</span> {{ $order->shipment->courier ?? '-' }}</div>
                <div><span class="text-stone-500">No. Resi:</span> <strong>{{ $order->shipment->tracking_number }}</strong></div>
            </div>
        </div>
    @endif

    @if($order->status === 'selesai')
        @if($order->return)
            @php $rs = $order->return->stage; @endphp
            <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h2 class="font-bold">Retur</h2>
                    <p class="text-sm text-stone-600">{{ ['pending' => 'Pengajuan retur sedang ditinjau.', 'approved' => 'Retur disetujui, menunggu refund.', 'refunded' => 'Retur selesai, refund sudah ditransfer.', 'rejected' => 'Pengajuan retur ditolak.'][$rs] }}</p>
                </div>
                <a href="{{ route('customer.returns.show', $order->return->id) }}" class="text-sm text-orange-600 hover:underline">Lihat detail →</a>
            </div>
        @elseif($returnEligible)
            <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h2 class="font-bold">Ada masalah dengan pesanan?</h2>
                    <p class="text-sm text-stone-600">Retur bisa diajukan sampai {{ $order->return_deadline->format('d M Y H:i') }}.</p>
                </div>
                <a href="{{ route('customer.returns.create', $order->order_number) }}" class="border border-stone-300 hover:bg-stone-50 px-5 py-2 rounded-lg text-sm font-medium">Ajukan Retur</a>
            </div>
        @endif
    @endif

    @if($order->status === 'selesai')
        <div class="bg-white rounded-lg border border-stone-200 p-6 mb-6">
            <h2 class="font-bold mb-1">Testimoni</h2>
            @if($order->testimonial)
                <p class="text-sm text-stone-600">Terima kasih sudah menulis testimoni.
                    {{ $order->testimonial->is_approved ? 'Testimoni kamu tampil di website.' : 'Sedang ditinjau admin sebelum tampil.' }}</p>
            @else
                <p class="text-sm text-stone-500 mb-4">Bagaimana pengalamanmu dengan pesanan ini?</p>
                <form method="POST" action="{{ route('customer.orders.testimonial', $order->order_number) }}" class="space-y-3">
                    @csrf
                    <select name="rating" class="border border-stone-300 rounded-lg px-3 py-2" required>
                        @for($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} bintang</option>@endfor
                    </select>
                    <textarea name="content" rows="3" required minlength="10" maxlength="1000" placeholder="Tulis pengalamanmu (min. 10 karakter)"
                              class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ old('content') }}</textarea>
                    @error('content') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg text-sm">Kirim Testimoni</button>
                </form>
            @endif
        </div>
    @endif

    @if($order->status === 'dikirim')
        <form method="POST" action="{{ route('customer.orders.received', $order->order_number) }}" class="mb-6">
            @csrf
            <button type="submit" onclick="return confirm('Pesanan sudah kamu terima?')"
                    class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-medium transition">
                Pesanan Sudah Diterima
            </button>
        </form>
    @endif

    <div class="text-center mb-4">
        <a href="{{ route('customer.orders.invoice', $order->order_number) }}" target="_blank"
           class="text-sm text-orange-600 hover:underline">Unduh Invoice (PDF)</a>
    </div>

    <div class="text-center">
        <a href="{{ route('customer.orders.index') }}" 
           class="text-sm text-stone-500 hover:text-stone-700">
            ← Kembali ke Daftar Pesanan
        </a>
    </div>
</div>

@if(($onlineEnabled ?? false))
    <script src="{{ $midtrans->snapJsUrl() }}" data-client-key="{{ $midtrans->clientKey() }}"></script>
    <script>
    (function () {
        const payBtn = document.getElementById('payBtn'), checkBtn = document.getElementById('checkBtn'), live = document.getElementById('payLive');
        if (!payBtn) return;
        const statusUrl = @json(route('customer.orders.payment-status', $order->order_number));
        const csrf = document.querySelector('meta[name=csrf-token]')?.content;
        const tones = { ok: 'border-green-200 bg-green-50 text-green-800', wait: 'border-blue-200 bg-blue-50 text-blue-800', warn: 'border-amber-200 bg-amber-50 text-amber-900', error: 'border-red-200 bg-red-50 text-red-800', info: 'border-stone-200 bg-stone-50 text-stone-700' };
        let timer = null, polls = 0;
        const MAX_POLLS = 100;                       // sekitar 5 menit

        function say(msg, tone) { live.className = 'mb-4 rounded-lg border p-4 text-sm ' + (tones[tone] || tones.info); live.textContent = msg; }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function resetBtn() { payBtn.disabled = false; payBtn.textContent = 'Bayar Sekarang'; }

        async function poll() {
            try {
                const j = await (await fetch(statusUrl, { headers: { Accept: 'application/json' } })).json();
                if (j.state === 'paid') { say(j.message, 'ok'); stop(); setTimeout(() => location.reload(), 1200); return; }
                if (j.done) { say(j.message, j.state === 'review' ? 'warn' : 'info'); stop(); if (['expired', 'failed'].includes(j.state)) resetBtn(); return; }
                say(j.message || 'Menunggu konfirmasi pembayaran…', 'wait');
            } catch (e) { /* jaringan putus sesaat: coba lagi pada putaran berikutnya */ }
            if (++polls >= MAX_POLLS) { stop(); say('Belum ada konfirmasi. Jika Anda sudah membayar, status akan terbarui otomatis dan Anda tidak perlu membayar lagi. Tekan “Cek status pembayaran” untuk memeriksa lagi.', 'info'); }
        }
        function startPolling() { stop(); polls = 0; poll(); timer = setInterval(poll, 3000); }

        payBtn.addEventListener('click', async () => {
            payBtn.disabled = true; payBtn.textContent = 'Menyiapkan…';
            try {
                const res = await fetch(payBtn.dataset.start, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                const j = await res.json();
                if (!res.ok) { say(j.message || 'Gagal memulai pembayaran.', 'error'); resetBtn(); return; }
                payBtn.disabled = false; payBtn.textContent = 'Lanjutkan pembayaran';
                if (window.snap) {
                    window.snap.pay(j.token, { onSuccess: startPolling, onPending: startPolling, onClose: startPolling, onError: () => say('Pembayaran gagal. Silakan coba lagi.', 'error') });
                } else if (j.redirect_url) {
                    window.open(j.redirect_url, '_blank', 'noopener'); startPolling();
                }
            } catch (e) { say('Koneksi bermasalah. Silakan coba lagi.', 'error'); resetBtn(); }
        });
        checkBtn.addEventListener('click', startPolling);
        @if(request('payment') === 'finish' || ($hasLive ?? false)) startPolling(); @endif
    })();
    </script>
@endif
@endsection