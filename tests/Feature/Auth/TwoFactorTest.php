<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Totp;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        config(['twofactor.enforced' => true]);
    }

    private function staff(string $role = 'admin', bool $twoFactor = false): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        if ($twoFactor) {
            $u->forceFill([
                'two_factor_secret' => Totp::generateSecret(),
                'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $u;
    }

    public function test_totp_cocok_dengan_vektor_rfc6238(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');

        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame('081804', Totp::code($secret, 1111111109));
        $this->assertNotNull(Totp::verify($secret, '287082', 59));
        $this->assertNull(Totp::verify($secret, '000000', 59));
        $this->assertNull(Totp::verify($secret, 'abcdef', 59));
    }

    public function test_admin_tanpa_2fa_diarahkan_ke_setup(): void
    {
        $this->actingAs($this->staff())->get('/admin')->assertRedirect(route('two-factor.setup'));
    }

    public function test_admin_dengan_2fa_diminta_kode_dulu(): void
    {
        $this->actingAs($this->staff('admin', true))->get('/admin')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_customer_tidak_terkena_2fa(): void
    {
        $c = User::factory()->create();
        $c->assignRole('customer');

        $this->actingAs($c)->get('/admin')->assertForbidden();
    }

    public function test_setup_mengaktifkan_2fa_dan_memberi_kode_pemulihan(): void
    {
        $u = $this->staff();

        $this->actingAs($u)->get(route('two-factor.setup'))->assertOk();
        $secret = session('two_factor_pending');

        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($u->fresh()->hasTwoFactor());

        $this->post(route('two-factor.confirm'), ['code' => Totp::code($secret)])
            ->assertRedirect(route('two-factor.manage'))
            ->assertSessionHas('recovery_codes');

        $u->refresh();
        $this->assertTrue($u->hasTwoFactor());
        $this->assertSame($secret, $u->two_factor_secret);
        $this->assertCount(8, $u->two_factor_recovery_codes);
        $this->get('/admin')->assertOk();
    }

    public function test_kode_benar_meloloskan_dan_tidak_bisa_dipakai_ulang(): void
    {
        $u = $this->staff('admin', true);
        $code = Totp::code($u->two_factor_secret);

        $this->actingAs($u)->post(route('two-factor.verify'), ['code' => $code])->assertRedirect();
        $this->get('/admin')->assertOk();

        // sesi lain memakai kode yang sama -> ditolak (replay)
        $this->flushSession();
        $this->actingAs($u)->post(route('two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_kode_salah_ditolak(): void
    {
        $u = $this->staff('admin', true);

        $this->actingAs($u)->post(route('two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->get('/admin')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_kode_pemulihan_sekali_pakai(): void
    {
        $u = $this->staff('admin', true);

        $this->actingAs($u)->post(route('two-factor.verify'), ['recovery_code' => 'AAAAA-BBBBB'])->assertRedirect();
        $this->get('/admin')->assertOk();
        $this->assertSame(['ccccc-ddddd'], $u->fresh()->two_factor_recovery_codes);

        $this->flushSession();
        $this->actingAs($u)->post(route('two-factor.verify'), ['recovery_code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('recovery_code');
    }

    public function test_opsional_bila_tidak_diwajibkan(): void
    {
        config(['twofactor.enforced' => false]);

        $this->actingAs($this->staff())->get('/admin')->assertOk();
        $this->flushSession();
        $this->actingAs($this->staff('staff', true))->get('/admin')->assertRedirect(route('two-factor.challenge'));
    }

    public function test_reset_butuh_password_dan_kode(): void
    {
        $u = $this->staff('admin', true);
        $this->actingAs($u)->withSession(['two_factor_ok' => $u->id]);

        $this->post(route('two-factor.reset'), ['password' => 'salah', 'code' => Totp::code($u->two_factor_secret)])
            ->assertSessionHasErrorsIn('reset', 'password');
        $this->assertTrue($u->fresh()->hasTwoFactor());

        $this->post(route('two-factor.reset'), ['password' => 'password', 'code' => Totp::code($u->two_factor_secret)])
            ->assertRedirect(route('two-factor.setup'));
        $this->assertFalse($u->fresh()->hasTwoFactor());
    }

    public function test_command_reset_menghapus_2fa(): void
    {
        $u = $this->staff('admin', true);

        $this->artisan('2fa:reset', ['email' => $u->email])->assertSuccessful();
        $this->assertFalse($u->fresh()->hasTwoFactor());
    }
}
