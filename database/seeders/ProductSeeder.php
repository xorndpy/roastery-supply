<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        $brands = Brand::all();

        $products = [
            ['name' => 'Espresso Machine Pro', 'sku' => 'RS-ESP-001', 'price' => 15000000],
            ['name' => 'Grinder Commercial', 'sku' => 'RS-GRD-001', 'price' => 8000000],
            ['name' => 'Milk Frother Steam', 'sku' => 'RS-MLK-001', 'price' => 3500000],
            ['name' => 'Espresso Machine Basic', 'sku' => 'RS-ESP-002', 'price' => 7000000],
            ['name' => 'Grinder Home', 'sku' => 'RS-GRD-002', 'price' => 2500000],
            ['name' => 'Portafilter 58mm', 'sku' => 'RS-TLS-001', 'price' => 800000],
        ];

        foreach ($products as $i => $p) {
            Product::create([
                'category_id' => $categories->random()->id,
                'brand_id' => $brands->random()->id,
                'name' => $p['name'],
                'slug' => \Str::slug($p['name']),
                'sku' => $p['sku'],
                'description' => 'Produk berkualitas untuk kedai kopi profesional. ' . $p['name'],
                'price' => $p['price'],
                'weight' => 5000,
                'stock' => rand(5, 20),
                'stock_threshold' => 3,
                'is_active' => true,
                'is_featured' => $i < 4,
            ]);
        }
    }
}