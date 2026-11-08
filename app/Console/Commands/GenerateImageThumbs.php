<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Buat thumbnail 480px (dengan trim ruang putih) untuk foto produk yang sudah ada. Foto asli tidak diubah. Aman diulang. */
class GenerateImageThumbs extends Command
{
    protected $signature = 'images:thumbs {--force : Buat ulang walau thumbnail sudah ada}';

    protected $description = 'Buat thumbnail 480px untuk semua foto produk';

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Ekstensi GD belum aktif.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $made = 0;
        $skipped = 0;

        foreach (ProductImage::query()->cursor() as $img) {
            $path = $img->image;
            $thumb = thumb_path($path);

            if (! $this->option('force') && $disk->exists($thumb)) {
                $skipped++;
                continue;
            }
            if (! $disk->exists($path)) {
                $this->warn("Hilang: {$path}");
                continue;
            }

            $src = @imagecreatefromstring($disk->get($path));
            if (! $src) {
                $this->warn("Tidak bisa dibaca: {$path}");
                continue;
            }

            // Buang ruang putih di tepi foto, margin seragam (lihat App\Support\ImageTrim).
            $src = \App\Support\ImageTrim::apply($src);

            $w = imagesx($src);
            $h = imagesy($src);
            $nw = min(480, $w);
            $nh = (int) round($h * ($nw / $w));

            $canvas = imagecreatetruecolor($nw, $nh);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            ob_start();
            match ($ext) {
                'png' => imagepng($canvas, null, 7),
                'webp' => imagewebp($canvas, null, 80),
                default => imagejpeg($canvas, null, 80),
            };
            $disk->put($thumb, ob_get_clean());

            $made++;
            $this->line("OK {$thumb}");
        }

        $this->info("{$made} thumbnail dibuat, {$skipped} sudah ada.");

        return self::SUCCESS;
    }
}
