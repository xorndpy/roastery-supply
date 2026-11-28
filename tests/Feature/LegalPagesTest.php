<?php

namespace Tests\Feature;

use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_kebijakan_dapat_dibuka(): void
    {
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Kebijakan Privasi')->assertSee('Hak Anda');
        $this->get(route('legal.terms'))->assertOk()->assertSee('Syarat &amp; Ketentuan', false)->assertSee('24 jam')
            ->assertSee('Retur, garansi, dan servis')->assertSee(ReturnService::WINDOW_DAYS . ' hari');
        $this->get('/kebijakan-retur-garansi')->assertNotFound();
    }

    public function test_footer_menautkan_ke_halaman_kebijakan(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.terms'), false);
    }

    public function test_pendaftaran_wajib_menyetujui_syarat(): void
    {
        $this->post('/register', [
            'name' => 'Test User', 'email' => 'tanpa-setuju@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('terms');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'tanpa-setuju@example.com']);
    }

    public function test_halaman_register_menampilkan_checkbox_persetujuan(): void
    {
        $this->get('/register')->assertOk()->assertSee('name="terms"', false);
    }

    public function test_halaman_404_memakai_tampilan_kustom(): void
    {
        $this->get('/halaman-yang-tidak-ada-12345')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Kembali ke beranda');
    }
}
