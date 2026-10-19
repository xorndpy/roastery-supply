<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pembungkus tipis untuk API Midtrans (Snap + Status API). Tidak memakai SDK supaya alurnya mudah dibaca
 * dan dijelaskan: setiap pemanggilan HTTP ada di satu berkas ini.
 *
 * Referensi: docs.midtrans.com (Snap Integration Guide, Handling Notifications, Get Transaction Status).
 */
class MidtransService
{
    public function configured(): bool
    {
        return filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key'));
    }

    public function isProduction(): bool
    {
        return (bool) config('services.midtrans.is_production');
    }

    public function clientKey(): ?string
    {
        return config('services.midtrans.client_key');
    }

    public function snapJsUrl(): string
    {
        return $this->isProduction() ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    private function snapEndpoint(): string
    {
        return $this->isProduction() ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    private function apiBase(): string
    {
        return $this->isProduction() ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }

    /**
     * Apakah status harus dikonfirmasi ke API Midtrans sebelum pesanan diubah?
     * Default: ya di produksi (rekomendasi Midtrans), tidak di sandbox/lokal agar simulasi offline tetap bisa.
     */
    public function verifiesWithApi(): bool
    {
        $v = config('services.midtrans.verify_status_api');

        return ($v === null || $v === '') ? $this->isProduction() : filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    // ---------------------------------------------------------------- tanda tangan notifikasi

    /** SHA512(order_id + status_code + gross_amount + ServerKey), rumus resmi Midtrans. */
    public function signatureFor(string $orderId, string $statusCode, string $grossAmount): string
    {
        return hash('sha512', $orderId . $statusCode . $grossAmount . config('services.midtrans.server_key'));
    }

    public function validSignature(array $payload): bool
    {
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $k) {
            if (!isset($payload[$k]) || !is_scalar($payload[$k])) {
                return false;
            }
        }
        if (!filled(config('services.midtrans.server_key'))) {
            return false;
        }

        $expected = $this->signatureFor((string) $payload['order_id'], (string) $payload['status_code'], (string) $payload['gross_amount']);

        return hash_equals($expected, strtolower((string) $payload['signature_key']));
    }

    // ---------------------------------------------------------------- Snap

    /**
     * Buat sesi pembayaran Snap. order_id di Midtrans = provider_order_id milik percobaan ini
     * (bukan nomor pesanan), karena Midtrans tidak mengizinkan order_id yang sama dipakai ulang.
     *
     * @return array{token: string, redirect_url: string}
     */
    public function createSnap(Order $order, Payment $payment): array
    {
        $order->loadMissing(['items', 'user']);

        $gross = (int) round((float) $payment->amount);
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'id'       => substr((string) ($item->product_sku ?: $item->product_id), 0, 50),
                'name'     => mb_substr((string) $item->product_name, 0, 50),
                'price'    => (int) round((float) $item->price),
                'quantity' => (int) $item->quantity,
            ];
        }
        if ((float) $order->shipping_cost > 0) {
            $items[] = ['id' => 'ONGKIR', 'name' => 'Ongkos kirim', 'price' => (int) round((float) $order->shipping_cost), 'quantity' => 1];
        }
        if ((float) $order->discount > 0) {
            $items[] = ['id' => 'DISKON', 'name' => 'Diskon', 'price' => -(int) round((float) $order->discount), 'quantity' => 1];
        }

        $body = [
            'transaction_details' => ['order_id' => $payment->provider_order_id, 'gross_amount' => $gross],
            'customer_details'    => array_filter([
                'first_name' => mb_substr((string) ($order->shipping_name ?: $order->user?->name ?: 'Pelanggan'), 0, 50),
                'email'      => $order->user?->email,
                'phone'      => $order->shipping_phone,
            ]),
            'expiry'              => ['unit' => 'minutes', 'duration' => $this->minutesUntil($payment)],
            'callbacks'           => ['finish' => route('customer.orders.show', $order->order_number) . '?payment=finish'],
            'custom_field1'       => $order->order_number,
        ];

        // Midtrans menolak bila jumlah item_details berbeda dari gross_amount; kirim hanya bila cocok.
        if ($items && array_sum(array_map(fn ($i) => $i['price'] * $i['quantity'], $items)) === $gross) {
            $body['item_details'] = $items;
        }

        $res = $this->http()->post($this->snapEndpoint(), $body);
        if (!$res->successful() || !$res->json('token')) {
            throw new RuntimeException('Midtrans menolak pembuatan sesi pembayaran (HTTP ' . $res->status() . '): ' . mb_substr((string) $res->body(), 0, 300));
        }

        return ['token' => $res->json('token'), 'redirect_url' => (string) $res->json('redirect_url')];
    }

    private function minutesUntil(Payment $payment): int
    {
        return max(1, (int) ceil(now()->diffInSeconds($payment->expires_at, false) / 60));
    }

    // ---------------------------------------------------------------- Status API

    /**
     * Status terkini sebuah transaksi di Midtrans, atau null bila transaksinya belum ada.
     * Catatan: Midtrans membalas HTTP 200 dengan status_code "404" di dalam body untuk transaksi yang belum ada
     * (pelanggan belum memilih metode bayar), jadi keduanya harus diperlakukan sebagai "belum ada".
     */
    public function status(string $orderId): ?array
    {
        $res = $this->http()->get($this->apiBase() . '/v2/' . rawurlencode($orderId) . '/status');

        if ($res->status() === 404) {
            return null;
        }
        if (!$res->successful()) {
            throw new RuntimeException('Midtrans Status API gagal (HTTP ' . $res->status() . ').');
        }

        $json = $res->json();
        if (!is_array($json) || (string) ($json['status_code'] ?? '') === '404' || !isset($json['transaction_status'])) {
            return null;
        }

        return $json;
    }

    /** Matikan transaksi yang masih pending di Midtrans agar tidak bisa dibayar lagi. Best-effort. */
    public function expire(string $orderId): void
    {
        try {
            $this->http()->post($this->apiBase() . '/v2/' . rawurlencode($orderId) . '/expire');
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
