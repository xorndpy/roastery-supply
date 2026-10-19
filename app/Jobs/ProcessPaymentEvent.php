<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\Payments\PaymentEventProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Memproses satu notifikasi pembayaran di antrean ("terjadwal").
 *
 * Lapisan pengaman:
 *  1. WithoutOverlapping: dua job untuk pesanan yang sama tidak berjalan bersamaan.
 *  2. Kunci baris database di OrderService: bila lapisan 1 lolos, lapisan ini tetap menjamin satu pemenang.
 *  3. Pemrosesan idempoten: event yang sudah selesai tidak diproses lagi.
 *  4. Coba ulang bertahap (backoff). Bila tetap gagal, pembayaran ditandai agar admin tahu.
 */
class ProcessPaymentEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /** true bila dijalankan langsung di dalam request (tanpa antrean); kegagalan lalu ditangani oleh retry gateway + pengecekan terjadwal. */
    public bool $inline = false;

    public function __construct(public int $eventId)
    {
    }

    /**
     * Titik masuk tunggal untuk menjadwalkan pemrosesan satu event.
     * Default: langsung (koneksi 'sync'), sehingga TIDAK butuh worker dan tetap melewati middleware yang sama.
     * MIDTRANS_PROCESS_INLINE=false: masuk antrean (butuh queue:work) dengan retry bertahap.
     */
    public static function run(int $eventId): void
    {
        if (config('services.midtrans.process_inline', true)) {
            $job = new static($eventId);
            $job->inline = true;
            dispatch($job)->onConnection('sync');

            return;
        }

        static::dispatch($eventId)->afterCommit();
    }

    public function backoff(): array
    {
        return [5, 30, 120, 600, 1800];
    }

    public function middleware(): array
    {
        $ref = PaymentEvent::whereKey($this->eventId)->value('provider_order_id') ?: ('event-' . $this->eventId);

        return [(new WithoutOverlapping('pay-' . $ref))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(PaymentEventProcessor $processor): void
    {
        $event = PaymentEvent::find($this->eventId);
        if (!$event || $event->processed_at) {
            return;
        }

        $event->increment('attempts');

        try {
            $processor->process($event);
        } catch (Throwable $e) {
            $event->update(['outcome_note' => 'Percobaan ' . $event->attempts . ' gagal: ' . mb_substr($e->getMessage(), 0, 300)]);
            throw $e;      // antrean akan mencoba lagi sesuai backoff
        }
    }

    /** Semua percobaan habis: tandai pembayaran supaya muncul di "Perlu perhatian". */
    public function failed(Throwable $e): void
    {
        $event = PaymentEvent::find($this->eventId);
        if (!$event) {
            return;
        }
        if ($this->inline) {
            // Mode langsung hanya sekali coba: request akan dibalas 5xx sehingga gateway mengulang, dan pengecekan
            // terjadwal ikut menjaring. Belum perlu mengganggu admin.
            $event->update(['outcome_note' => 'Gagal (akan dicoba ulang): ' . mb_substr($e->getMessage(), 0, 400)]);

            return;
        }
        $event->update(['outcome' => 'gagal_diproses', 'outcome_note' => mb_substr($e->getMessage(), 0, 500)]);

        Payment::where('provider_order_id', $event->provider_order_id)
            ->whereIn('status', ['waiting', 'review'])
            ->whereNull('review_reason')
            ->update(['review_reason' => 'gagal_diproses', 'reviewed_at' => null]);
    }
}
