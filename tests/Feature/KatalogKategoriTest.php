<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KatalogKategoriTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, array $extra = []): Category
    {
        return Category::unguarded(fn () => Category::create(array_merge(['name' => $name, 'slug' => str($name)->slug(), 'is_active' => true], $extra)));
    }

    private function product(Category $c, string $name): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => $name, 'slug' => "kp-$n", 'sku' => "KP-$n", 'price' => 1000, 'stock' => 5, 'category_id' => $c->id, 'is_active' => true]));
    }

    public function test_halaman_kategori_menampilkan_banner_nama_dan_pil_sub_kategori(): void
    {
        $parent = $this->category('Coffee Machine', ['banner' => 'categories/banners/cm.jpg']);
        $child = $this->category('Fully Automatic', ['parent_id' => $parent->id]);
        $this->product($child, 'Mesin Otomatis');

        $html = $this->get(route('products.index', ['category' => 'coffee-machine']))->assertOk()->getContent();

        $this->assertStringContainsString('storage/categories/banners/cm.jpg', $html);
        $this->assertStringContainsString('Coffee Machine', $html);
        $this->assertStringContainsString('aria-label="Sub-kategori"', $html);
        $this->assertStringContainsString('Fully Automatic', $html);
        $this->assertStringContainsString('Mesin Otomatis', $html);
    }

    public function test_sub_kategori_aktif_tetap_menampilkan_pil_saudaranya(): void
    {
        $parent = $this->category('Grinder');
        $a = $this->category('Grinder Kopi', ['parent_id' => $parent->id]);
        $b = $this->category('Grinder Rempah', ['parent_id' => $parent->id]);
        $this->product($a, 'Grinder A');

        $html = $this->get(route('products.index', ['category' => 'grinder-kopi']))->assertOk()->getContent();

        $this->assertStringContainsString('Semua Grinder', $html);
        $this->assertStringContainsString('Grinder Rempah', $html);
    }

    public function test_tanpa_banner_kategori_memakai_foto_header_umum_lalu_polos(): void
    {
        $cat = $this->category('Aksesori');
        $this->product($cat, 'Tamper');

        $plain = $this->get(route('products.index', ['category' => 'aksesori']))->assertOk()->getContent();
        $this->assertStringNotContainsString('storage/umum/header.jpg', $plain);
        $this->assertStringContainsString('Aksesori', $plain);

        DB::table('settings')->insert(['key' => 'header_bg', 'value' => 'umum/header.jpg', 'group' => 'Tampilan', 'created_at' => now(), 'updated_at' => now()]);
        \App\Models\Setting::forgetMemo();

        $this->get(route('products.index', ['category' => 'aksesori']))->assertOk()->assertSee('storage/umum/header.jpg');
    }

    public function test_pencarian_tanpa_kategori_tetap_memakai_header_biasa(): void
    {
        $this->get(route('products.index', ['search' => 'espresso']))->assertOk()->assertSee('Hasil untuk');
    }
}
