<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /** Halaman tantangan: minta kode 6 digit saat login. */
    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }
        if ($this->passed($request)) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }

        $request->validate(['code' => 'nullable|string|max:32', 'recovery_code' => 'nullable|string|max:32']);

        $ok = $request->filled('recovery_code')
            ? $this->useRecoveryCode($user, (string) $request->input('recovery_code'))
            : $this->checkCode($user, (string) $request->input('code'));

        if (!$ok) {
            return back()->withErrors([$request->filled('recovery_code') ? 'recovery_code' : 'code' => 'Kode tidak valid atau sudah dipakai.']);
        }

        $request->session()->regenerate();
        $request->session()->put('two_factor_ok', $user->id);

        return redirect()->intended(route('dashboard'));
    }

    /** Pemasangan: tampilkan QR + kunci manual. Rahasia disimpan di sesi sampai kode pertama terbukti benar. */
    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return redirect()->route('two-factor.manage');
        }

        $secret = $request->session()->get('two_factor_pending');
        if (!$secret) {
            $secret = Totp::generateSecret();
            $request->session()->put('two_factor_pending', $secret);
        }

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'uri' => Totp::uri(config('twofactor.issuer'), $user->email, $secret),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $request->session()->get('two_factor_pending');
        $request->validate(['code' => 'required|string|max:12']);

        if (!$secret || Totp::verify($secret, (string) $request->input('code')) === null) {
            return back()->withErrors(['code' => 'Kode tidak cocok. Pastikan jam HP Anda akurat lalu coba lagi.']);
        }

        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        activity('user')->performedOn($user)->causedBy($user)->log('2FA diaktifkan');

        $request->session()->forget('two_factor_pending');
        $request->session()->regenerate();
        $request->session()->put('two_factor_ok', $user->id);

        return redirect()->route('two-factor.manage')->with('recovery_codes', $codes);
    }

    public function manage(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }
        if (!$this->passed($request)) {
            return redirect()->guest(route('two-factor.challenge'));
        }

        return view('auth.two-factor-manage', [
            'codesLeft' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor() && $this->passed($request), 403);
        $request->validate(['password' => 'required|current_password']);

        $codes = $this->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('two-factor.manage')->with('recovery_codes', $codes);
    }

    /** Ganti HP / authenticator: butuh password + kode saat ini, lalu langsung pasang ulang. */
    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor() && $this->passed($request), 403);
        $request->validateWithBag('reset', ['password' => 'required|current_password', 'code' => 'required|string|max:12']);

        if (!$this->checkCode($user, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode tidak valid atau sudah dipakai.'], 'reset');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        activity('user')->performedOn($user)->causedBy($user)->log('2FA diatur ulang');

        $request->session()->forget(['two_factor_ok', 'two_factor_pending']);

        return redirect()->route('two-factor.setup');
    }

    private function passed(Request $request): bool
    {
        return $request->session()->get('two_factor_ok') === $request->user()->id;
    }

    /** Kode TOTP benar dan belum pernah dipakai (cegah replay di jendela 30 detik). */
    private function checkCode(User $user, string $code): bool
    {
        $step = Totp::verify((string) $user->two_factor_secret, $code);

        return $step !== null && Cache::add("2fa:used:{$user->id}:{$step}", 1, 150);
    }

    private function useRecoveryCode(User $user, string $input): bool
    {
        $input = strtolower(trim($input));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $code) {
            if (hash_equals($code, $input)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> 8 kode sekali pakai, format xxxxx-xxxxx */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtolower(Str::random(5) . '-' . Str::random(5)))
            ->all();
    }
}
