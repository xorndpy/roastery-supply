<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lacak resi lewat RajaOngkir/Komerce: POST https://rajaongkir.komerce.id/api/v1/track/waybill
 * (header `key`, parameter awb, courier, last_phone_number). Memakai API key yang sama dengan ongkir (RAJAONGKIR_API_KEY).
 * Bila paket sudah diterima, pesanan otomatis diselesaikan (garansi ikut terbit). Tanpa API key, timeline tetap
 * berjalan dari status internal pesanan; hanya perjalanan paket dari kurir yang tidak tersedia.
 */
class TrackingService
{
    /** Nama kurir di config/couriers.php -> kode RajaOngkir. */
    private const CODES = [
        'jne' => 'jne', 'jt' => 'jnt', 'jnt' => 'jnt', 'sicepat' => 'sicepat', 'anteraja' => 'anteraja', 'ninja' => 'ninja',
        'ninjaxpress' => 'ninja', 'idexpress' => 'ide', 'ide' => 'ide', 'pos' => 'pos', 'posindonesia' => 'pos', 'tiki' => 'tiki',
        'lionparcel' => 'lion', 'lion' => 'lion', 'wahana' => 'wahana', 'sap' => 'sap', 'sapexpress' => 'sap', 'sentralcargo' => 'sentral',
    ];

    public function __construct(private OrderService $orders) {}

    public function enabled(): bool
    {
        return (string) config('services.rajaongkir.key') !== '';
    }

    public static function courierCode(?string $courier): ?string
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower((string) $courier));

        return self::CODES[$key] ?? null;
    }

    /** @return bool true bila data dari kurir berhasil diambil */
    public function sync(Shipment $shipment): bool
    {
        $shipment->loadMissing('order');
        $code = self::courierCode($shipment->courier);

        if (! $this->enabled() || ! $shipment->tracking_number || ! $code) {
            return false;
        }

        $params = [
            'awb' => $shipment->tracking_number,
            'courier' => $code,
            'last_phone_number' => substr(preg_replace('/\D+/', '', (string) $shipment->order?->shipping_phone), -5) ?: null,
        ];
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');

        try {
            $res = Http::withHeaders(['key' => (string) config('services.rajaongkir.key')])->asForm()->timeout(12)
                ->post(config('services.rajaongkir.track_url').'?'.http_build_query($params), $params);
        } catch (Throwable $e) {
            report($e);
            $shipment->forceFill(['tracking_checked_at' => now()])->save();

            return false;
        }

        $data = $res->json('data');
        if (! $res->successful() || ! is_array($data)) {
            Log::info('Lacak resi tidak berhasil', ['status' => $res->status(), 'order' => $shipment->order_id]);
            $shipment->forceFill(['tracking_checked_at' => now()])->save();

            return false;
        }

        $events = collect($data['manifest'] ?? [])
            ->map(fn ($m) => [
                'at' => trim(($m['manifest_date'] ?? '').' '.($m['manifest_time'] ?? '')),
                'desc' => trim((string) ($m['manifest_description'] ?? $m['desc'] ?? '')),
                'city' => $m['city_name'] ?? $m['location'] ?? null,
            ])
            ->filter(fn ($e) => $e['desc'] !== '')
            ->sortByDesc('at')->values()->all();

        $status = (string) ($data['summary']['status'] ?? data_get($data, 'delivery_status.status') ?? ($events[0]['desc'] ?? ''));
        $delivered = ($data['delivered'] ?? false) === true
            || str_contains(strtoupper($status), 'DELIVERED')
            || strtoupper((string) data_get($data, 'delivery_status.status')) === 'DELIVERED';

        $shipment->forceFill(['tracking_status' => mb_substr($status, 0, 120) ?: null, 'tracking_events' => $events, 'tracking_checked_at' => now()])->save();

        if ($delivered && $shipment->order && $shipment->order->status === 'dikirim') {
            try {
                $this->orders->complete($shipment->order);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return true;
    }
}
