<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menu mobile berupa panel samping (dialog), konsisten di halaman Home dan Produk. */
class MobileDrawerTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_products_use_side_drawer_not_fullscreen_menu(): void
    {
        foreach ([route('home'), route('products.index')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('role="dialog"', false)
                ->assertSee('aria-modal="true"', false)
                ->assertSee('Tutup menu')
                ->assertSee('x-focus-trap="open"', false)
                ->assertDontSee('fixed inset-0 z-50 overflow-y-auto bg-white', false);
        }
    }

    public function test_hamburger_announces_dialog_and_expanded_state(): void
    {
        $this->get(route('home'))->assertSee('aria-haspopup="dialog"', false)->assertSee(':aria-expanded="open.toString()"', false);
    }

    public function test_shop_drawer_shows_login_buttons_for_guests_and_account_for_members(): void
    {
        $this->get(route('products.index'))->assertSee('Masuk')->assertSee('Daftar')->assertDontSee('Keluar');

        $this->actingAs(User::factory()->create())->get(route('products.index'))->assertSee('Akun Saya')->assertSee('Keluar');
    }

    public function test_home_drawer_marks_current_page(): void
    {
        $this->get(route('home'))->assertSee('aria-current="page"', false);
    }
}
