<?php

namespace App\Support;

use App\Models\City;
use App\Models\Province;
use Illuminate\Support\Facades\Cache;

class Regions
{
    /**
     * Data untuk dropdown provinsi -> kota (dikirim ke halaman sebagai JSON, tanpa request tambahan).
     *
     * @return array{provinces: array<int,array{id:int,name:string}>, cities: array<int,array<int,array{id:int,name:string}>>}
     */
    public static function picker(): array
    {
        return Cache::remember('regions.picker', 86400, function () {
            return [
                'provinces' => Province::orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->all(),
                'cities'    => City::orderBy('name')->get(['id', 'province_id', 'name'])
                    ->groupBy('province_id')
                    ->map(fn ($g) => $g->map->only(['id', 'name'])->values()->all())
                    ->all(),
            ];
        });
    }
}
