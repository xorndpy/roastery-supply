<?php

use Illuminate\Support\Facades\Storage;

if (! function_exists('media_url')) {
    /**
     * URL publik file upload (foto produk, logo, banner, dll).
     * Mengikuti disk 'public': lokal (APP_URL/storage/...) atau Cloudflare R2 (R2_PUBLIC_URL/...).
     */
    function media_url(?string $path): string
    {
        return Storage::disk('public')->url(ltrim((string) $path, '/'));
    }
}

if (! function_exists('thumb_path')) {
    /** Path thumbnail 480px untuk gambar produk: products/abc.webp -> products/abc_480.webp */
    function thumb_path(string $path): string
    {
        return preg_replace('/(\.[A-Za-z0-9]+)$/', '_480$1', $path) ?? $path;
    }
}

if (! function_exists('thumb_url')) {
    function thumb_url(?string $path): string
    {
        return $path ? media_url(thumb_path($path)) : '';
    }
}

if (! function_exists('wib')) {
    /**
     * Tampilkan waktu dalam WIB (database & zona waktu aplikasi tetap UTC, jadi konversi dilakukan saat tampil).
     * Dipakai di halaman pesanan/pengiriman agar jam sama dengan yang dilihat pembeli.
     */
    function wib($date, string $format = 'd M Y, H:i', bool $suffix = false): string
    {
        if (!$date) {
            return '-';
        }
        $d = $date instanceof \Carbon\CarbonInterface ? $date : \Illuminate\Support\Carbon::parse($date);

        return $d->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat($format) . ($suffix ? ' WIB' : '');
    }
}
