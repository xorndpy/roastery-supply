<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config(['services.google.client_id' => 'cid.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
    }

    private function googleCallback(array $profile = [], string $state = 'state-123', array $session = [])
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'openidconnect.googleapis.com/*' => Http::response(array_merge([
                'sub' => 'g-1', 'email' => 'budi@gmail.com', 'email_verified' => true, 'name' => 'Budi', 'picture' => 'https://x/p.png',
            ], $profile)),
        ]);

        return $this->withSession(array_merge(['google_oauth_state' => $state], $session))
            ->get(route('auth.google.callback', ['code' => 'abc', 'state' => $state]));
    }

    public function test_google_button_only_appears_when_configured(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Lanjutkan dengan Google');
        $this->configure();
        $this->get('/login')->assertOk()->assertSee('Lanjutkan dengan Google');
        $this->get('/register')->assertOk()->assertSee('Lanjutkan dengan Google');
    }

    public function test_redirect_goes_to_google_with_state_and_scope(): void
    {
        $this->configure();

        $res = $this->get(route('auth.google.redirect'));
        $res->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $res->assertRedirectContains('client_id=cid.apps.googleusercontent.com');
        $res->assertRedirectContains('scope=openid+email+profile');
        $this->assertSame(40, strlen(session('google_oauth_state')));
        $res->assertRedirectContains('state=' . session('google_oauth_state'));
    }

    public function test_disabled_when_not_configured(): void
    {
        $this->get(route('auth.google.redirect'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_new_google_user_is_created_as_verified_customer(): void
    {
        $this->configure();

        $this->googleCallback()->assertRedirect(route('dashboard'));

        $user = User::where('email', 'budi@gmail.com')->first();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-1', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertSame(1, User::count());
    }

    public function test_existing_email_is_linked_not_duplicated(): void
    {
        $this->configure();
        $existing = User::factory()->create(['email' => 'budi@gmail.com', 'password' => 'password-lama-1']);
        $hash = $existing->password;

        $this->googleCallback();

        $this->assertSame(1, User::count());
        $this->assertSame('g-1', $existing->fresh()->google_id);
        $this->assertSame($hash, $existing->fresh()->password); // akun terverifikasi: password tidak disentuh
        $this->assertAuthenticatedAs($existing);
    }

    public function test_unverified_local_account_gets_its_password_rotated_on_link(): void
    {
        $this->configure();
        // skenario pembajakan: penyerang mendaftar dengan email korban (belum terverifikasi)
        $squatted = User::factory()->unverified()->create(['email' => 'budi@gmail.com', 'password' => 'password-penyerang']);

        $this->googleCallback();

        $this->assertFalse(Hash::check('password-penyerang', $squatted->fresh()->password));
        $this->assertNotNull($squatted->fresh()->email_verified_at);
    }

    public function test_returning_user_is_matched_by_google_id_even_if_email_changed(): void
    {
        $this->configure();
        $u = User::factory()->create(['email' => 'lama@example.com']);
        $u->forceFill(['google_id' => 'g-1'])->save();

        $this->googleCallback(['email' => 'baru@gmail.com']);

        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($u);
    }

    public function test_invalid_state_is_rejected_without_calling_google(): void
    {
        $this->configure();
        Http::fake();

        $this->withSession(['google_oauth_state' => 'asli'])
            ->get(route('auth.google.callback', ['code' => 'abc', 'state' => 'palsu']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_missing_state_in_session_is_rejected(): void
    {
        $this->configure();
        Http::fake();

        $this->get(route('auth.google.callback', ['code' => 'abc', 'state' => 'x']))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_cancelling_at_google_is_handled(): void
    {
        $this->configure();

        $this->withSession(['google_oauth_state' => 's'])
            ->get(route('auth.google.callback', ['error' => 'access_denied', 'state' => 's']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->configure();

        $this->googleCallback(['email_verified' => false])->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_token_exchange_failure_is_handled(): void
    {
        $this->configure();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->withSession(['google_oauth_state' => 's'])
            ->get(route('auth.google.callback', ['code' => 'bad', 'state' => 's']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deleted_account_cannot_log_in_with_google(): void
    {
        $this->configure();
        $u = User::factory()->create(['email' => 'budi@gmail.com']);
        $u->delete();

        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_returns_to_checkout_after_google_login(): void
    {
        $this->configure();

        $this->googleCallback([], 's', ['url.intended' => route('customer.checkout.index')])
            ->assertRedirect(route('customer.checkout.index'));
    }
}
