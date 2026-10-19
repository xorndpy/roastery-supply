<?php

namespace App\Services\Payments;

use App\Models\PaymentEvent;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Mencatat setiap notifikasi gateway SEBELUM diproses.
 *
 * Mengapa: gateway boleh mengirim notifikasi yang sama berkali-kali (retry, atau dua kali sekaligus).
 * Kejadian yang sama selalu menghasilkan event_key yang sama, dan indeks UNIQUE (provider, event_key)
 * di database hanya mengizinkan SATU baris. Pencegahan duplikat jadi dijamin database, bukan if-else di kode,
 * sehingga tetap aman walau dua request tiba pada milidetik yang sama.
 */
class PaymentEventRecorder
{
    /** Midtrans tidak mengirim ID notifikasi, jadi identitas kejadian = gabungan field yang menentukan statusnya. */
    public function eventKey(array $p): string
    {
        return sha1(implode('|', [
            $p['order_id'] ?? '',
            $p['transaction_id'] ?? '',
            $p['transaction_status'] ?? '',
            $p['status_code'] ?? '',
            $p['fraud_status'] ?? '',
            $p['gross_amount'] ?? '',
        ]));
    }

    /**
     * @return array{0: PaymentEvent, 1: bool}  [event, apakah baru dibuat]
     */
    public function record(array $payload, string $source = 'webhook'): array
    {
        $key = $this->eventKey($payload);

        try {
            $event = PaymentEvent::create([
                'provider'           => 'midtrans',
                'event_key'          => $key,
                'provider_order_id'  => isset($payload['order_id']) ? (string) $payload['order_id'] : null,
                'transaction_status' => $payload['transaction_status'] ?? null,
                'fraud_status'       => $payload['fraud_status'] ?? null,
                'source'             => $source,
                'signature_valid'    => true,
                'payload'            => $payload,
            ]);

            return [$event, true];
        } catch (UniqueConstraintViolationException) {
            return [PaymentEvent::where('provider', 'midtrans')->where('event_key', $key)->firstOrFail(), false];
        }
    }

    /** Notifikasi dengan tanda tangan salah: dicatat sebagai jejak, tidak pernah diproses. */
    public function recordInvalid(array $payload, ?string $ip = null): void
    {
        PaymentEvent::updateOrCreate(
            ['provider' => 'midtrans', 'event_key' => 'invalid:' . sha1(json_encode($payload))],
            [
                'provider_order_id'  => isset($payload['order_id']) && is_scalar($payload['order_id']) ? mb_substr((string) $payload['order_id'], 0, 64) : null,
                'transaction_status' => isset($payload['transaction_status']) && is_scalar($payload['transaction_status']) ? mb_substr((string) $payload['transaction_status'], 0, 30) : null,
                'source'             => 'webhook',
                'signature_valid'    => false,
                'payload'            => ['ip' => $ip, 'body' => array_diff_key($payload, ['signature_key' => 1])],
                'outcome'            => 'ditolak_signature',
                'processed_at'       => now(),
            ],
        );
    }
}
