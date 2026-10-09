<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class GoogleController extends Controller
{
    public function __construct(private GoogleOAuth $google)
    {
    }

    public function redirect(Request $request)
    {
        if (!$this->google->enabled()) {
            return $this->fail('Login Google belum diaktifkan.');
        }

        // state = pengaman CSRF: harus kembali sama dari Google.
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($this->google->authUrl($state));
    }

    public function callback(Request $request)
    {
        if (!$this->google->enabled()) {
            return $this->fail('Login Google belum diaktifkan.');
        }

        $expected = (string) $request->session()->pull('google_oauth_state', '');
        if ($expected === '' || !hash_equals($expected, (string) $request->query('state'))) {
            return $this->fail('Sesi login Google tidak valid. Silakan coba lagi.');
        }

        if ($request->query('error') || !$request->query('code')) {
            return $this->fail('Login Google dibatalkan.');
        }

        try {
            $profile = $this->google->profile((string) $request->query('code'));
        } catch (RuntimeException $e) {
            report($e);

            return $this->fail('Login Google gagal. Silakan coba lagi.');
        }

        if (!$profile['verified']) {
            return $this->fail('Email Google kamu belum terverifikasi.');
        }

        $user = $this->resolveUser($profile);

        if ($user === null) {
            return $this->fail('Akun ini dinonaktifkan. Hubungi admin.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Cari akun: google_id -> email -> buat baru. Mengembalikan null jika akun sudah dihapus.
     */
    private function resolveUser(array $p): ?User
    {
        $user = User::withTrashed()->where('google_id', $p['id'])->first()
            ?? User::withTrashed()->where('email', $p['email'])->first();

        if ($user) {
            if ($user->trashed()) {
                return null;
            }

            // Akun lokal dengan email yang BELUM terverifikasi bisa saja dibuat orang lain
            // memakai email ini. Putar password-nya supaya password lama tidak berlaku lagi.
            if ($user->email_verified_at === null) {
                $user->password = Str::random(64);
            }

            $user->forceFill([
                'google_id'         => $user->google_id ?: $p['id'],
                'avatar'            => $user->avatar ?: $p['avatar'],
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();

            return $user;
        }

        $user = new User(['name' => $p['name'], 'email' => $p['email'], 'password' => Str::random(40)]);
        $user->forceFill([
            'google_id'         => $p['id'],
            'avatar'            => $p['avatar'],
            'email_verified_at' => now(),
        ])->save();

        $user->assignRole(Role::findOrCreate('customer', 'web'));

        return $user;
    }

    private function fail(string $message)
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
