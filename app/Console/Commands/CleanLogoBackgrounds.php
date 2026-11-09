<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Partner;
use App\Support\ImageBackground;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Terapkan penghapusan latar polos ke logo & foto kategori yang SUDAH diunggah sebelumnya. */
class CleanLogoBackgrounds extends Command
{
    protected $signature = 'images:clean-logos {--dry-run : Hanya tampilkan hasilnya, tanpa mengubah apa pun}';

    protected $description = 'Hapus latar polos pada logo merek, logo rekanan, dan foto kategori yang sudah ada.';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dry = (bool) $this->option('dry-run');
        $changed = $skipped = $missing = 0;

        foreach ([[Brand::class, 'logo', 'brands'], [Partner::class, 'logo', 'partners'], [Category::class, 'image', 'categories']] as [$model, $column, $dir]) {
            $model::query()->whereNotNull($column)->where($column, '!=', '')->each(function ($row) use ($disk, $dry, $column, $dir, &$changed, &$skipped, &$missing) {
                $path = $row->{$column};
                if (!$disk->exists($path)) {
                    $missing++;
                    $this->warn("hilang    {$path}");

                    return;
                }

                $png = ImageBackground::process((string) $disk->get($path));
                if ($png === null) {
                    $skipped++;
                    $this->line("dilewati  {$path}");

                    return;
                }

                if (!$dry) {
                    $new = $dir . '/' . Str::random(40) . '.png';
                    $disk->put($new, $png);
                    $row->forceFill([$column => $new])->saveQuietly();
                    $disk->delete($path);
                }
                $changed++;
                $this->info(($dry ? 'akan diubah ' : 'diubah     ') . $path);
            });
        }

        $this->newLine();
        $this->info("Selesai: {$changed} " . ($dry ? 'akan diubah' : 'diubah') . ", {$skipped} dilewati (latar tidak polos/gelap/sudah transparan), {$missing} file hilang.");

        return self::SUCCESS;
    }
}
