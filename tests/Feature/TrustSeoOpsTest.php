<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrustSeoOpsTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value): void
    {
        DB::table('settings')->insert(['key' => $key, 'value' => $value, 'group' => 'general', 'created_at' => now(), 'updated_at' => now()]);
        Setting::forgetMemo();
    }

    // ------------------------------------------------------------ persetujuan cookie

    public function test_banner_cookie_tampil_bila_ga4_aktif_dan_skrip_tidak_dimuat_langsung(): void
    {
        $this->setting('ga4_id', 'G-ABC123XYZ9');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('id="koff-cookie"', false)
            ->assertSee('Tolak')
            ->assertSee('Terima')
            ->assertDontSee('<script async src="https://www.googletagmanager.com', false);
    }

    public function test_banner_cookie_tidak_tampil_bila_tidak_ada_pelacak(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('id="koff-cookie"', false);
    }

    // ------------------------------------------------------------ SEO

    public function test_beranda_punya_canonical_dan_open_graph(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('application/ld+json', false);
    }

    public function test_sitemap_memuat_halaman_utama_dan_kebijakan(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<urlset', false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('products.index'), false);
    }

    public function test_robots_txt_menutup_area_privat_dan_menunjuk_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /customer/', $robots);
        $this->assertStringContainsString('Sitemap:', $robots);
    }

    // ------------------------------------------------------------ header keamanan

    public function test_header_keamanan_terpasang(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_area_privat_diberi_noindex(): void
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    // ------------------------------------------------------------ preflight

    public function test_preflight_gagal_bila_debug_menyala(): void
    {
        config(['app.debug' => true, 'app.key' => 'base64:' . base64_encode(random_bytes(32))]);

        $this->artisan('app:preflight')->assertExitCode(1);
    }

    public function test_preflight_lolos_bila_pengaturan_aman(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:' . base64_encode(random_bytes(32)),
            'app.url' => 'https://contoh.test',
            'session.secure' => true,
            'mail.default' => 'smtp',
        ]);

        $this->artisan('app:preflight')->assertExitCode(0);
    }
}
