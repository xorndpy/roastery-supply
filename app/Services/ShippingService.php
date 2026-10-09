<?php

namespace App\Services;

use App\Models\City;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;

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
        return ShippingMethod::active()->whereHas('rates')->exists();
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
    public function options(City $city, int $grams, float|int $subtotal): array
    {
        $kg = $this->billableKg($grams);
        $options = [];

        $methods = ShippingMethod::active()->orderBy('sort_order')->orderBy('id')->get();

        foreach ($methods as $method) {
            $rate = $this->rateFor($method, $city);
            if (!$rate) {
                continue;
            }

            $options[] = $this->build($method, $rate, $kg, (float) $subtotal);
        }

        return $options;
    }

    /** Hitung satu layanan; null bila layanan nonaktif/tidak melayani kota itu. */
    public function quote(int $methodId, City $city, int $grams, float|int $subtotal): ?array
    {
        $method = ShippingMethod::active()->find($methodId);
        if (!$method) {
            return null;
        }

        $rate = $this->rateFor($method, $city);

        return $rate ? $this->build($method, $rate, $this->billableKg($grams), (float) $subtotal) : null;
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
            'cost'        => $free ? 0 : $cost,
            'etd'         => $rate->etd_label,
            'free'        => $free,
            'weight_kg'   => $kg,
        ];
    }
}
