<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

/**
 * KHUSUS DEMO / DEVELOPMENT. Tarif di bawah hanya contoh agar checkout bisa dicoba.
 * Jangan dipakai di produksi: atur tarif asli lewat Admin > Tarif Pengiriman.
 *
 *   php artisan db:seed --class=ShippingDemoSeeder
 */
class ShippingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WilayahSeeder::class);

        $method = ShippingMethod::firstOrCreate(
            ['name' => 'Kargo Reguler (contoh)'],
            ['description' => 'Tarif contoh untuk demo', 'sort_order' => 1, 'is_active' => true]
        );

        // Contoh sederhana: Jawa lebih murah, luar Jawa lebih mahal.
        $java = ['31', '32', '33', '34', '35', '36'];

        foreach (Province::all() as $province) {
            ShippingRate::updateOrCreate(
                ['shipping_method_id' => $method->id, 'province_id' => $province->id, 'city_id' => null],
                in_array($province->code, $java, true)
                    ? ['rate_per_kg' => 9000, 'min_charge' => 25000, 'etd_min' => 2, 'etd_max' => 4]
                    : ['rate_per_kg' => 25000, 'min_charge' => 60000, 'etd_min' => 5, 'etd_max' => 10]
            );
        }
    }
}
