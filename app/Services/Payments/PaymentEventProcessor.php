<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menerjemahkan status dari gateway menjadi perubahan pada pembayaran/pesanan.
 *
 * Aturan emas: pesanan HANYA berubah lewat OrderService::settleGatewayPayment() (pintu yang sama dengan
 * konfirmasi manual admin). Kelas ini hanya memutuskan "apa arti status ini", lalu menyerahkannya.
 * Semua perubahan memeriksa status TERKINI di dalam kunci baris, jadi notifikasi yang terlambat atau
 * tidak berurutan (pending tiba setelah settlement) tidak bisa memundurkan status yang sudah final.
 */
class PaymentEventProcessor
{
    public function __construct(private MidtransService $midtrans, private OrderService $orders)
    {
    }

    public function process(PaymentEvent $event): string
    {
        $event->refresh();
        if ($event->processed_at) {
            return (string) $event->outcome;       // sudah selesai: pemrosesan ulang = tidak melakukan apa pun
        }

        $payment = Payment::where('provider', 'midtrans')->where('provider_order_id', $event->provider_order_id)->first();
        if (!$payment) {
            return $this->finish($event, 'order_tidak_dikenal');
        }
        $event->update(['payment_id' => $payment->id]);

        $facts = $event->payload ?? [];
        $note = null;

        // Rekomendasi Midtrans: jangan percaya isi notifikasi mentah, konfirmasi ke Status API.
        if ($event->source === 'webhook' && $this->midtrans->verifiesWithApi()) {
            $api = $this->midtrans->status((string) $event->provider_order_id);
            if ($api === null) {
                throw new RuntimeException('Transaksi belum ditemukan di Midtrans (akan dicoba lagi).');
            }
            if (($api['transaction_status'] ?? null) !== ($facts['transaction_status'] ?? null)) {
                $note = "Notifikasi berstatus '" . ($facts['transaction_status'] ?? '?') . "', Midtrans melaporkan '" . $api['transaction_status'] . "'. Memakai status dari Midtrans.";
            }
            $facts = $api;
        }

        $status = (string) ($facts['transaction_status'] ?? '');
        $fraud = $facts['fraud_status'] ?? null;

        $paid = $status === 'settlement' || ($status === 'capture' && $fraud === 'accept');

        if ($paid) {
            $this->syncFields($payment, $facts);
            $r = $this->orders->settleGatewayPayment($payment->id, [
                'gross_amount' => $facts['gross_amount'] ?? null,
                'paid_at'      => $this->paidAt($facts),
            ]);

            return $this->finish($event, $r['outcome'], $note ?? $this->reasonNote($r['reason']));
        }

        if ($status === 'capture' && $fraud === 'challenge') {
            $this->syncFields($payment, $facts);

            return $this->finish($event, $this->hold($payment, 'fraud_challenge', ['waiting', 'expired']), $note);
        }

        return match (true) {
            $status === 'pending'                                              => $this->finish($event, $this->pending($payment, $facts), $note),
            $status === 'expire'                                               => $this->finish($event, $this->close($payment, 'expired', 'Sesi pembayaran kedaluwarsa di gateway.'), $note),
            in_array($status, ['deny', 'failure'], true)                       => $this->finish($event, $this->close($payment, 'rejected', "Ditolak oleh gateway ($status)."), $note),
            $status === 'cancel'                                               => $this->finish($event, $this->cancelled($payment), $note),
            in_array($status, ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'], true) => $this->finish($event, $this->afterPaid($payment), $note),
            default                                                            => $this->finish($event, 'status_tidak_dikenal', "Status '$status' tidak dikenali."),
        };
    }

    // ------------------------------------------------------------------ transisi

    private function pending(Payment $payment, array $facts): string
    {
        return DB::transaction(function () use ($payment, $facts) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($p->status !== 'waiting') {
                return 'diabaikan_sudah_final';       // mis. pending yang terlambat setelah settlement
            }
            $this->fill($p, $facts, 'pending');

            return 'menunggu_pembayaran';
        });
    }

    /** expire/deny/failure: hanya menutup percobaan yang masih menunggu. */
    private function close(Payment $payment, string $status, string $note): string
    {
        return DB::transaction(function () use ($payment, $status, $note) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($p->status !== 'waiting') {
                return 'diabaikan_sudah_final';
            }
            $p->update(['status' => $status, 'gateway_status' => $status === 'expired' ? 'expire' : 'deny', 'note' => $note]);

            return $status === 'expired' ? 'kedaluwarsa' : 'ditolak_gateway';
        });
    }

    /** cancel: sebelum dibayar = menutup percobaan; sesudah dibayar = perlu ditinjau admin. */
    private function cancelled(Payment $payment): string
    {
        $current = Payment::whereKey($payment->id)->value('status');

        return in_array($current, ['verified', 'review', 'duplicate'], true)
            ? $this->afterPaid($payment)
            : $this->close($payment, 'rejected', 'Dibatalkan di gateway.');
    }

    /** refund/chargeback/cancel setelah dibayar: status pembayaran tetap, tetapi ditandai untuk ditinjau. */
    private function afterPaid(Payment $payment): string
    {
        return DB::transaction(function () use ($payment) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!in_array($p->status, ['verified', 'review', 'duplicate'], true)) {
                return 'diabaikan_sudah_final';
            }
            if ($p->review_reason === null || $p->reviewed_at !== null) {
                $p->update(['review_reason' => 'refund_chargeback', 'reviewed_at' => null]);
            }

            return 'perlu_perhatian';
        });
    }

    private function hold(Payment $payment, string $reason, array $fromStatuses): string
    {
        return DB::transaction(function () use ($payment, $reason, $fromStatuses) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!in_array($p->status, $fromStatuses, true)) {
                return 'diabaikan_sudah_final';
            }
            $p->update(['status' => 'review', 'review_reason' => $reason, 'reviewed_at' => null]);

            return 'perlu_perhatian';
        });
    }

    // ------------------------------------------------------------------ pendukung

    private function syncFields(Payment $payment, array $facts): void
    {
        DB::transaction(function () use ($payment, $facts) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            $this->fill($p, $facts, $facts['transaction_status'] ?? null);
        });
    }

    private function fill(Payment $p, array $facts, ?string $gatewayStatus): void
    {
        $attrs = array_filter([
            'payment_type'   => $facts['payment_type'] ?? null,
            'gateway_status' => $gatewayStatus,
        ]);

        $meta = array_intersect_key($facts, array_flip(['va_numbers', 'permata_va_number', 'bill_key', 'biller_code', 'bank', 'store', 'payment_code', 'issuer', 'acquirer', 'masked_card', 'card_type', 'expiry_time']));
        if ($meta) {
            $attrs['gateway_meta'] = array_merge($p->gateway_meta ?? [], $meta);
        }
        if (!empty($facts['transaction_id']) && $p->provider_transaction_id === null
            && !Payment::where('provider_transaction_id', $facts['transaction_id'])->where('id', '!=', $p->id)->exists()) {
            $attrs['provider_transaction_id'] = $facts['transaction_id'];
        }

        if ($attrs) {
            $p->update($attrs);
        }
    }

    private function paidAt(array $facts): ?Carbon
    {
        $raw = $facts['settlement_time'] ?? $facts['transaction_time'] ?? null;
        if (!$raw) {
            return null;
        }
        try {
            // Midtrans mengirim waktu dalam WIB.
            return Carbon::parse($raw, 'Asia/Jakarta')->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function reasonNote(?string $reason): ?string
    {
        return $reason ? (Payment::REASONS[$reason][0] ?? $reason) : null;
    }

    /**
     * Tulis hasil event. Pemroses pertama yang menyelesaikan menang; bila dua pemroses menangani event yang sama
     * bersamaan, yang kedua TIDAK menimpa hasil milik pemenang (UPDATE ... WHERE processed_at IS NULL bersifat atomik).
     */
    private function finish(PaymentEvent $event, string $outcome, ?string $note = null): string
    {
        $won = PaymentEvent::whereKey($event->id)->whereNull('processed_at')
            ->update(['outcome' => $outcome, 'outcome_note' => $note, 'processed_at' => now()]);

        return $won ? $outcome : (string) PaymentEvent::whereKey($event->id)->value('outcome');
    }
}
