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
