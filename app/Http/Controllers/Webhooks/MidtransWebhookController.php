<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentEvent;
use App\Services\Payments\MidtransService;
use App\Services\Payments\PaymentEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penerima notifikasi HTTP dari Midtrans.
 *
 * Sengaja SANGAT tipis dan cepat (Midtrans meminta balasan < 5 detik):
 *   verifikasi tanda tangan -> catat event (UNIQUE) -> serahkan ke antrean -> balas 200.
 * Tidak ada logika bisnis di sini.
 *
 * Kode balasan penting karena Midtrans mencoba ulang berdasarkan kode: 2xx = selesai (tidak diulang),
 * jadi notifikasi duplikat dan pesanan tak dikenal dibalas 200; hanya error server yang membuat 5xx.
 */
class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans, PaymentEventRecorder $recorder): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();

        if (!is_array($payload) || !$midtrans->validSignature($payload)) {
            $recorder->recordInvalid(is_array($payload) ? $payload : [], $request->ip());

            return response()->json(['status' => 'invalid_signature'], 403);
        }

        [$event, $created] = $recorder->record($payload, (string) $request->attributes->get('payment_source', 'webhook'));

        // Duplikat dari kejadian yang sudah selesai: tidak ada yang perlu dilakukan.
        if (!$created && $event->processed_at) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        // Baru, atau duplikat dari event yang tersangkut (mis. proses sebelumnya terhenti): jalankan pemrosesan.
        // Aman dipanggil berulang karena pemrosesan idempoten dan dikunci per pesanan.
        ProcessPaymentEvent::run($event->id);

        return response()->json(['status' => $created ? 'accepted' : 'duplicate'], 200);
    }
}
