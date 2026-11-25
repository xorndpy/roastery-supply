<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile. Aktif hanya bila TURNSTILE_SECRET_KEY terisi (lokal tanpa kunci = dilewati).
 * Bila server Cloudflare tidak terjangkau, pengguna asli tidak dihalangi (dicatat di log).
 */
class Turnstile implements ValidationRule
{
    /** Implisit: tetap dijalankan bila field kosong/tidak dikirim, supaya bot tidak bisa lolos dengan menghilangkan field. */
    public bool $implicit = true;

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.secret_key')) && filled(config('services.turnstile.site_key'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::enabled()) {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Selesaikan verifikasi keamanan dulu, lalu coba lagi.');

            return;
        }

        try {
            $res = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        if (! $res->ok()) {
            report(new \RuntimeException('Turnstile siteverify HTTP ' . $res->status()));

            return;
        }

        if (! $res->json('success')) {
            $fail('Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.');
        }
    }
}
