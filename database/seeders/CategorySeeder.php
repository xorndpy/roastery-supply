<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Espresso Machine',
                'description' => 'Mesin espresso komersial dan rumahan berstandar barista.',
            ],
            [
                'name' => 'Grinder',
                'description' => 'Penggiling kopi presisi untuk berbagai tingkat kehalusan seduhan.',
            ],
            [
                'name' => 'Milk Frother',
                'description' => 'Alat frothing dan steamer susu untuk kreasi latte art sempurna.',
            ],
            [
                'name' => 'Tools',
                'description' => 'Aksesoris dan peralatan barista lengkap (tamper, distributor, pitcher).',
            ],
            [
                'name' => 'Sparepart',
                'description' => 'Suku cadang original mesin kopi dan grinder.',
            ],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
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
