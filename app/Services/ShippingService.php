<?php

namespace App\Services;

use App\Models\City;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ongkos kirim dari tarif per wilayah yang diatur admin (bisa diganti RajaOngkir nanti
 * tanpa mengubah checkout: cukup ganti isi options()/quote()).
 *
 *  - berat tagihan = pembulatan ke atas ke kg, minimal 1 kg
 *  - ongkir = max(tarif minimum, tarif per kg x kg)
 *  - tarif kota (pengecualian) didahulukan; kalau tidak ada, tarif provinsi
 *  - layanan tanpa tarif untuk tujuan itu tidak ditawarkan
 *  - ambang gratis ongkir (Pengaturan > free_shipping_min): belanja >= ambang -> ongkir Rp 0
 */
class ShippingService
{
    /** Produk yang beratnya belum diisi dianggap segini (gram), agar ongkir tidak jatuh ke Rp 0. */
    public const DEFAULT_ITEM_WEIGHT_GRAMS = 1000;

    /** @param array<int,array{quantity:int|string, weight?:int|string|null}> $cart */
    public function totalWeight(array $cart): int
    {
        $total = 0;
        foreach ($cart as $line) {
            $weight = (int) ($line['weight'] ?? 0);
            $total += (int) $line['quantity'] * ($weight > 0 ? $weight : self::DEFAULT_ITEM_WEIGHT_GRAMS);
        }

        return $total;
    }

    public function billableKg(int $grams): int
    {
        return max(1, (int) ceil($grams / 1000));
    }

    /** Sudah ada minimal satu layanan aktif yang punya tarif? Bila belum, checkout berjalan tanpa ongkir (diatur manual). */
    public function configured(): bool
    {
        return $this->roEnabled() || ShippingMethod::active()->whereHas('rates')->exists();
    }

    public function freeShippingMin(): int
    {
        return (int) Setting::get('free_shipping_min', 0);
    }

    /**
     * Semua layanan yang tersedia ke kota tujuan.
     *
     * @return array<int,array{id:int,name:string,description:?string,cost:int,etd:?string,free:bool,weight_kg:int}>
     */
    public function options(City $city, int $grams, float|int $subtotal, ?string $postalCode = null): array
    {
        if ($this->roEnabled() && ($ro = $this->roOptions($city, $postalCode, $grams, (float) $subtotal))) {
            return $ro;
        }

        $kg = $this->billableKg($grams);
        $options = [];

        foreach (ShippingMethod::active()->orderBy('sort_order')->orderBy('id')->get() as $method) {
            if ($rate = $this->rateFor($method, $city)) {
                $options[] = $this->build($method, $rate, $kg, (float) $subtotal);
            }
        }

        return $options;
    }

    /** Hitung satu layanan; null bila layanan nonaktif/tidak melayani kota itu. */
    public function quote(int|string $methodId, City $city, int $grams, float|int $subtotal, ?string $postalCode = null): ?array
    {
        if (is_string($methodId) && str_starts_with($methodId, 'ro:')) {
            foreach ($this->roOptions($city, $postalCode, $grams, (float) $subtotal) as $o) {
                if ($o['id'] === $methodId) {
                    return $o;
                }
            }

            return null;
        }

        $method = ShippingMethod::active()->find((int) $methodId);
        if (!$method) {
            return null;
        }

        $rate = $this->rateFor($method, $city);

        return $rate ? $this->build($method, $rate, $this->billableKg($grams), (float) $subtotal) : null;
    }

    private const RO = 'https://rajaongkir.komerce.id/api/v1';

    private function roEnabled(): bool
    {
        return filled(config('services.rajaongkir.key')) && filled(config('services.rajaongkir.origin'));
    }

    /** @return array<int,array> kosong bila API gagal / tujuan tidak ketemu -> caller fallback ke tarif manual */
    private function roOptions(City $city, ?string $postal, int $grams, float $subtotal): array
    {
        try {
            $dest = $this->roDestination($city, $postal);
            if (!$dest) {
                return [];
            }

            $cfg = config('services.rajaongkir');
            $couriers = preg_replace('/[,\s]+/', ':', trim((string) $cfg['couriers']));

            $rows = Cache::remember("ro:cost:{$cfg['origin']}:{$dest}:{$grams}:{$couriers}", now()->addHours(6), function () use ($cfg, $dest, $grams, $couriers) {
                $res = Http::withHeaders(['key' => $cfg['key']])->asForm()->timeout(10)
                    ->post(self::RO . '/calculate/domestic-cost', [
                        'origin' => $cfg['origin'], 'destination' => $dest,
                        'weight' => $grams, 'courier' => $couriers, 'price' => 'lowest',
                    ]);
                if (!$res->successful()) {
                    throw new \RuntimeException('RajaOngkir ' . $res->status() . ': ' . $res->body());
                }

                return $res->json('data') ?? [];
            });
        } catch (\Throwable $e) {
            Log::warning('RajaOngkir gagal: ' . $e->getMessage());

            return [];
        }

        $threshold = $this->freeShippingMin();
        $free = $threshold > 0 && $subtotal >= $threshold;
        $kg = $this->billableKg($grams);
        $out = [];

        foreach ($rows as $r) {
            $cost = (int) ($r['cost'] ?? 0);
            if ($cost <= 0) {
                continue;
            }
            $etd = trim((string) ($r['etd'] ?? ''));
            $out[] = [
                'id'          => 'ro:' . strtolower($r['code']) . ':' . $r['service'],
                'name'        => strtoupper($r['code']) . ' ' . $r['service'],
                'description' => $r['description'] ?? null,
                'type'        => 'external',
                'cost'        => $free ? 0 : $cost,
                'etd'         => $etd !== '' ? preg_replace('/\bdays?\b/i', 'hari', $etd) : null,
                'free'        => $free,
                'weight_kg'   => $kg,
            ];
        }

        usort($out, fn ($a, $b) => $a['cost'] <=> $b['cost']);

        return $out;
    }

    /** ID tujuan Komerce: cari lewat kode pos (dicek kotanya cocok), fallback nama kota. */
    private function roDestination(City $city, ?string $postal): ?int
    {
        $postal = preg_match('/^\d{5}$/', (string) $postal) ? $postal : null;

        $id = Cache::remember("ro:dest:{$city->id}:" . ($postal ?? '-'), now()->addDays(30), function () use ($city, $postal) {
            $name = preg_replace('/^(Kota Administrasi|Kabupaten Administrasi|Kota|Kabupaten)\s+/i', '', $city->name);

            foreach (array_filter([$postal, $name]) as $term) {
                $res = Http::withHeaders(['key' => config('services.rajaongkir.key')])->timeout(10)
                    ->get(self::RO . '/destination/domestic-destination', ['search' => $term, 'limit' => 5, 'offset' => 0]);

                foreach ($res->json('data') ?? [] as $d) {
                    if (stripos((string) ($d['city_name'] ?? ''), $name) !== false) {
                        return (int) $d['id'];
                    }
                }
            }

            return null;
        });

        return $id ?: null;
    }

    private function rateFor(ShippingMethod $method, City $city): ?ShippingRate
    {
        $rates = ShippingRate::where('shipping_method_id', $method->id)
            ->where('province_id', $city->province_id)
            ->where(fn ($q) => $q->where('city_id', $city->id)->orWhereNull('city_id'))
            ->get();

        return $rates->firstWhere('city_id', $city->id) ?? $rates->firstWhere('city_id', null);
    }

    private function build(ShippingMethod $method, ShippingRate $rate, int $kg, float $subtotal): array
    {
        $cost = (int) ceil(max((float) $rate->min_charge, (float) $rate->rate_per_kg * $kg));

        $threshold = $this->freeShippingMin();
        $free = $threshold > 0 && $subtotal >= $threshold;

        return [
            'id'          => $method->id,
            'name'        => $method->name,
            'description' => $method->description,
            'type'        => $method->type ?: 'external',
            'cost'        => $free ? 0 : $cost,
            'etd'         => $rate->etd_label,
            'free'        => $free,
            'weight_kg'   => $kg,
        ];
    }
}
