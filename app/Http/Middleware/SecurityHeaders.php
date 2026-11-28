<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua respons, plus X-Robots-Tag: noindex untuk area privat.
 * Header yang sudah diisi oleh bagian lain aplikasi tidak ditimpa.
 */
class SecurityHeaders
{
    /** Jalur yang tidak boleh masuk hasil pencarian. */
    private const PRIVATE_PATHS = [
        'admin', 'admin/*', 'customer', 'customer/*', 'login', 'register', 'forgot-password',
        'reset-password', 'reset-password/*', 'verify-email', 'verify-email/*', 'confirm-password',
        'two-factor', 'two-factor/*', 'auth/*', 'email/*', 'webhooks/*', 'bandingkan',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $set = function (string $name, string $value) use ($h): void {
            if (! $h->has($name)) {
                $h->set($name, $value);
            }
        };

        $set('X-Content-Type-Options', 'nosniff');
        $set('X-Frame-Options', 'SAMEORIGIN');
        $set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        // HSTS hanya di produksi lewat HTTPS. 180 hari, tanpa includeSubDomains/preload agar mudah dibatalkan.
        if ($request->isSecure() && app()->environment('production')) {
            $set('Strict-Transport-Security', 'max-age=15552000');
        }

        if ($request->is(self::PRIVATE_PATHS)) {
            $set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
