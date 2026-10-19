<?php

namespace App\Services\Payments;

use App\Jobs\ProcessPaymentEvent;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Jaring pengaman: tidak semua yang terjadi di gateway sampai ke kita lewat webhook.
 *  - Webhook bisa hilang (server mati, jaringan).
 *  - Midtrans TIDAK mengirim webhook saat halaman Snap kedaluwarsa.
 * Maka pembayaran yang masih "waiting" ditanyakan langsung ke Status API. Hasilnya dicatat sebagai event
 * dengan event_key yang sama seperti webhook, jadi bila webhook-nya sudah pernah datang, hasil ini otomatis
 * dianggap duplikat dan tidak diproses ulang. Semua tetap lewat satu jalur pemrosesan yang sama.
 */
class PaymentReconciler
{
    public function __construct(
        private MidtransService $midtrans,
        private PaymentEventRecorder $recorder,
        private PaymentEventProcessor $processor,
    ) {
    }

    /**
     * Cek satu pembayaran ke gateway.
     *
     * @param  bool  $sync  true = proses sekarang juga (tombol admin); false = serahkan ke antrean (terjadwal)
     * @return string  gagal_cek | belum_ada | kedaluwarsa | sudah_diproses | diproses | dijadwalkan
     */
    public function check(Payment $payment, bool $sync = false): string
    {
        try {
            $api = $this->midtrans->status((string) $payment->provider_order_id);
        } catch (Throwable $e) {
            report($e);
            $payment->update(['last_checked_at' => now()]);

            return 'gagal_cek';
        }

        $payment->update(['last_checked_at' => now()]);

        if ($api === null) {
            // Transaksi belum pernah dibuat di Midtrans (pelanggan tidak memilih metode bayar).
            return $this->expireIfDue($payment) ? 'kedaluwarsa' : 'belum_ada';
        }

        [$event, $created] = $this->recorder->record($api, 'reconcile');

        if (!$created && $event->processed_at) {
            return 'sudah_diproses';
        }

        if ($sync) {
            $this->processor->process($event);

            return 'diproses';
        }

        ProcessPaymentEvent::run($event->id);

        return 'dijadwalkan';
    }

    /** Jalankan untuk semua pembayaran yang layak dicek + kirim ulang event yang tersangkut. */
    public function sweep(bool $force = false): array
    {
        $summary = ['dicek' => 0, 'tersangkut' => 0, 'kedaluwarsa' => 0, 'dijadwalkan' => 0];

        // Event yang masuk tetapi tak kunjung selesai (mis. antrean sempat mati).
        PaymentEvent::whereNull('processed_at')->where('signature_valid', true)
            ->where('created_at', '<=', now()->subMinutes(2))->limit(100)->pluck('id')
            ->each(function ($id) use (&$summary) {
                ProcessPaymentEvent::run((int) $id);
                $summary['tersangkut']++;
            });

        $q = Payment::where('provider', 'midtrans')->where('status', 'waiting');
        if (!$force) {
            $q->where('created_at', '<=', now()->subMinutes(2))
              ->where(fn ($w) => $w->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subMinutes(5)));
        }

        foreach ($q->orderBy('id')->limit(100)->get() as $payment) {
            $summary['dicek']++;
            $r = $this->check($payment);
            if ($r === 'kedaluwarsa') {
                $summary['kedaluwarsa']++;
            } elseif ($r === 'dijadwalkan') {
                $summary['dijadwalkan']++;
            }
        }

        return $summary;
    }

    private function expireIfDue(Payment $payment): bool
    {
        return DB::transaction(function () use ($payment) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($p->status !== 'waiting' || !$p->expires_at || $p->expires_at->isFuture()) {
                return false;
            }
            $p->update(['status' => 'expired', 'note' => 'Sesi pembayaran berakhir tanpa pembayaran.']);

            return true;
        });
    }
}
