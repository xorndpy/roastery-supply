<?php

namespace App\Support;

/** TOTP (RFC 6238, SHA-1, 6 digit, 30 detik) tanpa dependency. Kompatibel dengan Google Authenticator/Authy/1Password. */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $timestamp = null, int $offset = 0): string
    {
        $counter = intdiv($timestamp ?? time(), 30) + $offset;
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $counter), self::base32Decode($secret), true);
        $o = ord($hash[19]) & 0xF;
        $bin = ((ord($hash[$o]) & 0x7F) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);

        return str_pad((string) ($bin % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Cocokkan kode dengan toleransi +-1 langkah (selisih jam). Mengembalikan nomor langkah yang cocok
     * (dipakai untuk mencegah kode yang sama dipakai dua kali), atau null.
     */
    public static function verify(string $secret, string $input, ?int $timestamp = null, int $window = 1): ?int
    {
        $input = preg_replace('/\s+/', '', $input);
        if (!preg_match('/^\d{6}$/', $input)) {
            return null;
        }

        $step = intdiv($timestamp ?? time(), 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $timestamp, $i), $input)) {
                return $step + $i;
            }
        }

        return null;
    }

    public static function uri(string $issuer, string $account, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($b32, '='))) as $c) {
            $pos = strpos(self::ALPHABET, $c);
            if ($pos === false) {
                continue;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
