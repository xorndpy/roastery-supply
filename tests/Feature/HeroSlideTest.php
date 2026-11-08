<?php

namespace Tests\Feature;

use App\Models\HeroSlide;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroSlideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        Storage::fake('public');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    private function photos(int $n): array
    {
        return collect(range(1, $n))->map(fn ($i) => UploadedFile::fake()->image("foto{$i}.jpg", 1600, 900))->all();
    }

    public function test_admin_bisa_unggah_banyak_foto_sekaligus_berurutan(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.hero.store'), ['images' => $this->photos(3)])
            ->assertSessionHas('success');

        $this->assertSame([1, 2, 3], HeroSlide::orderBy('id')->pluck('sort_order')->all());
        $this->assertCount(3, Storage::disk('public')->files('hero'));
    }

    public function test_batas_enam_foto_dan_kelebihannya_dilewati(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.hero.store'), ['images' => $this->photos(5)]);
        $this->post(route('admin.hero.store'), ['images' => $this->photos(3)])->assertSessionHas('success');

        $this->assertSame(HeroSlide::MAX, HeroSlide::count());
        $this->post(route('admin.hero.store'), ['images' => $this->photos(1)])->assertSessionHasErrors('images');
    }

    public function test_file_bukan_gambar_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.hero.store'), ['images' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('images.0');
        $this->assertSame(0, HeroSlide::count());
    }

    public function test_urutan_toggle_dan_hapus(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.hero.store'), ['images' => $this->photos(3)]);
        [$a, $b, $c] = HeroSlide::orderBy('id')->get()->all();

        $this->postJson(route('admin.hero.reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertOk();
        $this->assertSame([$c->id, $a->id, $b->id], HeroSlide::orderBy('sort_order')->pluck('id')->all());

        $this->patch(route('admin.hero.toggle', $a->id));
        $this->assertFalse($a->fresh()->is_active);

        $this->delete(route('admin.hero.destroy', $b->id));
        $this->assertNull(HeroSlide::find($b->id));
        Storage::disk('public')->assertMissing($b->image);
    }

    public function test_beranda_menampilkan_slideshow_hanya_foto_aktif(): void
    {
        HeroSlide::create(['image' => 'hero/satu.jpg', 'sort_order' => 1, 'is_active' => true]);
        HeroSlide::create(['image' => 'hero/dua.jpg', 'sort_order' => 2, 'is_active' => true]);
        HeroSlide::create(['image' => 'hero/mati.jpg', 'sort_order' => 3, 'is_active' => false]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('storage/hero/satu.jpg', $html);
        $this->assertStringContainsString('storage/hero/dua.jpg', $html);
        $this->assertStringNotContainsString('storage/hero/mati.jpg', $html);
        $this->assertStringContainsString('Pilih foto latar', $html);   // penanda slideshow
    }

    public function test_satu_foto_tanpa_kontrol_slideshow(): void
    {
        HeroSlide::create(['image' => 'hero/satu.jpg', 'sort_order' => 1, 'is_active' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('storage/hero/satu.jpg', $html);
        $this->assertStringNotContainsString('Pilih foto latar', $html);
    }

    public function test_tanpa_izin_pengaturan_tidak_bisa_mengubah(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->post(route('admin.hero.store'), ['images' => $this->photos(1)])->assertForbidden();
    }

    public function test_pengaturan_bertab_menampilkan_semua_tab_dan_tautan_hero(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Profil Usaha')->assertSee('Pembayaran &amp; Pengiriman', false)->assertSee('Tampilan')->assertSee('SEO')
            ->assertSee(route('admin.hero.index'));
    }
}
