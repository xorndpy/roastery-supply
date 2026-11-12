<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gerbang 2FA untuk panel admin: belum aktif -> wajib setup; sudah aktif tapi belum verifikasi di sesi ini -> tantangan kode. */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        if (!$user->hasTwoFactor()) {
            if (config('twofactor.enforced') && $user->hasAnyRole(['super_admin', 'admin', 'staff', 'gudang', 'keuangan'])) {
                return redirect()->guest(route('two-factor.setup'));
            }

            return $next($request);
        }

        if ($request->session()->get('two_factor_ok') !== $user->id) {
            return redirect()->guest(route('two-factor.challenge'));
        }

        return $next($request);
    }
}
