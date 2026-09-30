<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            [
                'name' => 'La Marzocco',
                'description' => 'Mesin espresso legendaris buatan tangan dari Florence, Italia.',
            ],
            [
                'name' => 'Rocket',
                'description' => 'Mesin espresso premium handcrafted dari Milan untuk coffee enthusiast.',
            ],
            [
                'name' => 'Breville',
                'description' => 'Peralatan kopi inovatif dan serbaguna asal Australia.',
            ],
            [
                'name' => 'Mazzer',
                'description' => 'Standar emas grinder kopi komersial dari Venice, Italia.',
            ],
            [
                'name' => 'Rancilio',
                'description' => 'Solusi mesin espresso profesional dan andal untuk bisnis kafe.',
            ],
        ];

        foreach ($brands as $data) {
            Brand::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
