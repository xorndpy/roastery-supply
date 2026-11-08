<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KatalogPromoTest extends TestCase
{
    use RefreshDatabase;

    private function banner(string $title, string $placement): Banner
    {
        return Banner::create(['title' => $title, 'image' => "banners/{$title}.jpg", 'placement' => $placement, 'sort_order' => 1, 'is_active' => true]);
    }

    public function test_banner_tampil_sesuai_penempatan(): void
    {
        $this->banner('KHUSUS-BERANDA', 'beranda');
        $this->banner('KHUSUS-KATALOG', 'katalog');
        $this->banner('DI-KEDUANYA', 'semua');

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('KHUSUS-BERANDA', $home);
        $this->assertStringContainsString('DI-KEDUANYA', $home);
        $this->assertStringNotContainsString('KHUSUS-KATALOG', $home);

        $shop = $this->get(route('products.index'))->assertOk()->getContent();
        $this->assertStringContainsString('KHUSUS-KATALOG', $shop);
        $this->assertStringContainsString('DI-KEDUANYA', $shop);
        $this->assertStringNotContainsString('KHUSUS-BERANDA', $shop);
    }

    public function test_banner_lama_tanpa_penempatan_tetap_di_beranda(): void
    {
        $b = Banner::create(['title' => 'LAMA', 'image' => 'banners/lama.jpg', 'sort_order' => 1, 'is_active' => true]);

        $this->assertSame('beranda', $b->fresh()->placement);
        $this->get('/')->assertSee('LAMA');
        $this->get(route('products.index'))->assertDontSee('LAMA');
    }

    public function test_katalog_tanpa_promo_tidak_menampilkan_area_promo_dan_header_polos(): void
    {
        DB::table('settings')->insert(['key' => 'header_bg', 'value' => 'umum/umum.jpg', 'group' => 'Tampilan', 'created_at' => now(), 'updated_at' => now()]);
        \App\Models\Setting::forgetMemo();

        $html = $this->get(route('products.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-label="Promo"', $html);
        $this->assertStringNotContainsString('storage/umum/umum.jpg', $html);
        $this->assertStringContainsString('Kelebihan berbelanja di', $html);
    }

    public function test_banner_katalog_dibatasi_enam(): void
    {
        foreach (range(1, 8) as $i) {
            $this->banner("PROMO-{$i}", 'katalog');
        }

        $html = $this->get(route('products.index'))->assertOk()->getContent();

        $this->assertSame(6, preg_match_all('/alt="PROMO-\d"/', $html));
    }
}
