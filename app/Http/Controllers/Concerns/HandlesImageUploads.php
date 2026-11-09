<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use App\Support\ImageBackground;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesImageUploads
{
    protected function storeImage(UploadedFile $file, string $dir): string
    {
        return $file->store($dir, 'public');
    }

    /**
     * Untuk logo merek/rekanan dan foto kategori: latar polos dibuang otomatis (hasil PNG transparan).
     * Bila gambar tidak cocok diproses (foto, latar gelap, sudah transparan) atau GD gagal, file asli disimpan apa adanya.
     */
    protected function storeLogo(UploadedFile $file, string $dir): string
    {
        try {
            $png = ImageBackground::process((string) file_get_contents($file->getRealPath()));
            if ($png !== null) {
                $path = trim($dir, '/') . '/' . Str::random(40) . '.png';
                Storage::disk('public')->put($path, $png);

                return $path;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $file->store($dir, 'public');
    }

    protected function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
