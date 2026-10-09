<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * 38 provinsi + 514 kabupaten/kota dari database/data/wilayah.csv (lihat README di folder itu).
 * Aman dijalankan berulang: memperbarui nama, tidak menggandakan data, tidak menghapus apa pun.
 */
class WilayahSeeder extends Seeder
{
    public function run(): void
    {
        $handle = fopen(database_path('data/wilayah.csv'), 'r');
        fgetcsv($handle); // header

        $provinceIds = [];
        $cities = [];

        while (($row = fgetcsv($handle)) !== false) {
            [$code, $name, $parent] = array_pad($row, 3, '');
            if ($code === '') {
                continue;
            }

            if ($parent === '') {
                $provinceIds[$code] = Province::updateOrCreate(['code' => $code], ['name' => $name])->id;
            } else {
                $cities[] = [$code, $name, $parent];
            }
        }
        fclose($handle);

        foreach ($cities as [$code, $name, $parent]) {
            City::updateOrCreate(['code' => $code], ['province_id' => $provinceIds[$parent], 'name' => $name]);
        }

        Cache::forget('regions.picker');
    }
}
