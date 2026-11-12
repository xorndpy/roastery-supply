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

if (! function_exists('pdf_image_data_uri')) {
    /**
     * Gambar dari disk 'public' (lokal/R2/Supabase) -> data URI untuk DomPDF.
     * DomPDF tidak bisa memuat URL jarak jauh (remote dimatikan demi keamanan) dan tidak mendukung WEBP,
     * jadi WEBP dikonversi ke PNG dan gambar besar dikecilkan agar PDF tetap ringan. null = tidak ada/tidak terbaca.
     */
    function pdf_image_data_uri(?string $path, int $maxWidth = 600): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            $path = ltrim($path, '/');
            if (! $disk->exists($path)) {
                return null;
            }

            $bytes = $disk->get($path);
            $info = @getimagesizefromstring($bytes);
            if (! $info) {
                return null;
            }

            $mime = $info['mime'];
            $needsConvert = $mime === 'image/webp' || ($info[0] > $maxWidth && in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true));

            if ($needsConvert) {
                $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
                if ($img === false) {
                    return $mime === 'image/webp' ? null : 'data:'.$mime.';base64,'.base64_encode($bytes);
                }
                if ($info[0] > $maxWidth) {
                    $scaled = imagescale($img, $maxWidth);
                    if ($scaled !== false) {
                        $img = $scaled;
                    }
                }
                imagealphablending($img, false);
                imagesavealpha($img, true);
                ob_start();
                imagepng($img);
                $bytes = (string) ob_get_clean();
                $mime = 'image/png';
            } elseif (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
