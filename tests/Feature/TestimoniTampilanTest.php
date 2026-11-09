<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TestimoniTampilanTest extends TestCase
{
    use RefreshDatabase;

    private function testimonial(array $extra = []): Testimonial
    {
        return Testimonial::unguarded(fn () => Testimonial::create(array_merge(
            ['customer_name' => 'Budi Wicaksono', 'content' => 'Layanan servis cepat dan profesional.', 'rating' => 5, 'is_approved' => true, 'is_featured' => true],
            $extra
        )));
    }

    public function test_beranda_menampilkan_perusahaan_dan_foto_testimoni(): void
    {
        $this->testimonial(['company' => 'Founder · PT Artisan Coffee, Bandung', 'customer_photo' => 'testimonials/budi.jpg']);

        $this->get('/')->assertOk()
            ->assertSee('Founder · PT Artisan Coffee, Bandung')
            ->assertSee('storage/testimonials/budi.jpg');
    }

    public function test_tanpa_foto_tampil_inisial_dan_tanpa_perusahaan_tidak_ada_baris_kosong(): void
    {
        $this->testimonial();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('>BU<', $html);
        $this->assertStringNotContainsString('storage/testimonials/', $html);
    }

    public function test_admin_menyimpan_perusahaan_dan_foto_lalu_bisa_menghapus_foto(): void
    {
        Storage::fake('public');
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'customer_name' => 'Sari Rahayu', 'company' => 'F&B Manager · Hotel Bintang, Surabaya',
            'rating' => 5, 'content' => 'Pemesanan jauh lebih mudah dan cepat.', 'is_approved' => 1,
            'customer_photo' => UploadedFile::fake()->image('sari.jpg', 400, 400),
        ])->assertSessionHasNoErrors();

        $t = Testimonial::firstOrFail();
        $this->assertSame('F&B Manager · Hotel Bintang, Surabaya', $t->company);
        Storage::disk('public')->assertExists($t->customer_photo);

        $photo = $t->customer_photo;
        $this->put(route('admin.testimonials.update', $t->id), [
            'customer_name' => 'Sari Rahayu', 'company' => 'F&B Manager', 'rating' => 5,
            'content' => 'Pemesanan jauh lebih mudah dan cepat.', 'is_approved' => 1, 'remove_photo' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNull($t->fresh()->customer_photo);
        Storage::disk('public')->assertMissing($photo);
    }
}
