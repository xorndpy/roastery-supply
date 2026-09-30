<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Generate SKU otomatis: RS-[KATEGORI]-[NUMBER]
     */
    public function generateSku(string $categoryId): string
    {
        $category = \App\Models\Category::find($categoryId);
        $prefix = $category ? strtoupper(substr($category->slug, 0, 3)) : 'GEN';

        $lastProduct = Product::where('sku', 'like', "RS-{$prefix}-%")
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;
        if ($lastProduct) {
            $parts = explode('-', $lastProduct->sku);
            $number = (int) end($parts) + 1;
        }

        return sprintf('RS-%s-%03d', $prefix, $number);
    }

    /**
     * Generate slug unik
     */
    public function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Product::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Upload gambar produk + resize pakai GD native
     */
    public function uploadImage(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = 'products/' . $filename;

        // Coba resize pakai GD kalau extension ke-load
        if (extension_loaded('gd')) {
            try {
                $resized = $this->resizeImage($file, 1000);
                Storage::disk('public')->put($path, $resized);
                return $path;
            } catch (\Exception $e) {
                // Fallback: upload apa adanya kalau resize gagal
            }
        }

        // Fallback: simpan langsung tanpa resize
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Resize gambar pakai GD native (tanpa package tambahan)
     */
    protected function resizeImage(UploadedFile $file, int $maxWidth): string
    {
        $mime = $file->getMimeType();
        $sourcePath = $file->getRealPath();

        // Bikin resource dari file
        $source = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (!$source) {
            throw new \Exception('Format gambar tidak didukung.');
        }

        // Hitung dimensi baru
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) ($height * ($maxWidth / $width));
        } else {
            // Gak perlu resize
            $newWidth = $width;
            $newHeight = $height;
        }

        // Bikin canvas baru
        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // Handle transparansi PNG
        if ($mime === 'image/png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize
        imagecopyresampled(
            $canvas, $source,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Output ke buffer
        ob_start();
        match ($mime) {
            'image/jpeg', 'image/jpg' => imagejpeg($canvas, null, 85),
            'image/png' => imagepng($canvas, null, 8),
            'image/webp' => imagewebp($canvas, null, 85),
        };
        $output = ob_get_clean();

        // Cleanup
        imagedestroy($source);
        imagedestroy($canvas);

        return $output;
    }

    /**
     * Upload model 3D (.glb / .gltf)
     */
    public function uploadModel3d(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = 'models/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Hapus file dari storage
     */
    public function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Handle upload multi-gambar
     */
    public function syncImages(Product $product, array $images, ?array $keepIds = null): void
    {
        // Hapus gambar yang gak di-keep
        if ($keepIds !== null) {
            $toDelete = $product->images()->whereNotIn('id', $keepIds)->get();
            foreach ($toDelete as $img) {
                $this->deleteFile($img->image);
                $img->delete();
            }
        }

        // Upload gambar baru
        $sortOrder = $product->images()->max('sort_order') ?? 0;
        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $path = $this->uploadImage($image);
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $path,
                    'is_primary' => $product->images()->count() === 0,
                    'sort_order' => ++$sortOrder,
                ]);
            }
        }
    }
}