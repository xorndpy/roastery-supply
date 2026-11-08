<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Salin file upload lama (storage/app/public & storage/app/private) ke Cloudflare R2. Aman diulang. */
class MigrateMediaToR2 extends Command
{
    protected $signature = 'media:to-r2 {--dry-run : Hanya tampilkan yang akan disalin}';

    protected $description = 'Salin file upload lokal ke Cloudflare R2 (butuh variabel R2_* terisi)';

    public function handle(): int
    {
        if (! env('R2_BUCKET') || ! env('R2_PRIVATE_BUCKET')) {
            $this->error('R2_BUCKET dan R2_PRIVATE_BUCKET belum diisi di .env.');

            return self::FAILURE;
        }

        $pairs = [
            'public' => storage_path('app/public'),
            'local' => storage_path('app/private'),
        ];

        foreach ($pairs as $disk => $root) {
            if (! is_dir($root)) {
                $this->warn("Lewati {$root}: folder tidak ada.");
                continue;
            }

            $src = Storage::build(['driver' => 'local', 'root' => $root]);
            $dst = Storage::disk($disk); // sudah mengarah ke R2 karena env R2_* terisi
            $copied = 0;
            $skipped = 0;

            foreach ($src->allFiles() as $file) {
                if (str_starts_with(basename($file), '.')) {
                    continue; // .gitignore dll
                }
                if ($dst->exists($file)) {
                    $skipped++;
                    continue;
                }
                $this->line(($this->option('dry-run') ? '[dry] ' : '') . "{$disk}: {$file}");
                if (! $this->option('dry-run')) {
                    $dst->writeStream($file, $src->readStream($file));
                }
                $copied++;
            }

            $this->info("{$disk}: {$copied} disalin, {$skipped} sudah ada.");
        }

        return self::SUCCESS;
    }
}
