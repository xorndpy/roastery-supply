<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Setelah login, tiap role panel harus mendarat di panel admin (bukan area pelanggan/profil). */
class StaffLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['twofactor.enforced' => false]);
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userAs(string $role): User
    {
        $u = User::factory()->create(['password' => bcrypt('rahasia-123')]);
        $u->assignRole($role);

        return $u;
    }

    public function test_every_panel_role_lands_on_the_admin_dashboard_after_login(): void
    {
        foreach (['super_admin', 'admin', 'staff', 'gudang', 'keuangan'] as $role) {
            $user = $this->userAs($role);

            $this->post(route('login'), ['email' => $user->email, 'password' => 'rahasia-123'])
                ->assertRedirect(route('dashboard', absolute: false));

            $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));

            $this->post(route('logout'));
        }
    }

    public function test_customers_still_land_on_the_customer_dashboard(): void
    {
        $this->actingAs($this->userAs('customer'))->get(route('dashboard'))->assertRedirect(route('customer.dashboard'));
    }

    public function test_keuangan_can_actually_open_the_admin_dashboard_and_sees_money_but_not_other_areas(): void
    {
        $res = $this->actingAs($this->userAs('keuangan'))->get(route('admin.dashboard'));

        $res->assertOk()->assertSee('Pendapatan')->assertSee('Laba Rugi');
        $res->assertDontSee('Perlu di-follow-up'); // blok pelanggan hanya untuk yang berhak
    }
}
