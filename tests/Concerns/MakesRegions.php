<?php

namespace Tests\Concerns;

use App\Models\City;
use App\Models\Province;

trait MakesRegions
{
    /** Provinsi + kota minimal untuk tes (tanpa memuat 552 baris wilayah di setiap tes). */
    protected function region(string $provinceCode = '32', string $provinceName = 'Jawa Barat', string $cityCode = '32.73', string $cityName = 'Kota Bandung'): array
    {
        $province = Province::firstOrCreate(['code' => $provinceCode], ['name' => $provinceName]);
        $city = City::firstOrCreate(['code' => $cityCode], ['province_id' => $province->id, 'name' => $cityName]);

        return [$province, $city];
    }
}
