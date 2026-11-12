<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Diagnosa penyimpanan foto (Supabase/R2). Jalankan di terminal server (SSH / Terminal cPanel): php artisan media:check
 * Menulis file uji kecil, membukanya lewat URL publik seperti browser, lalu menghapusnya.
 */
class CheckMediaStorage extends Command
{
    protected $signature = 'media:check';

    protected $description = 'Cek apakah upload foto sampai ke Supabase/R2 dan bisa dibuka lewat URL publik.';

    public function handle(): int
    {
        $ok = true;

        $this->info('1) Variabel lingkungan (nilai rahasia disamarkan)');
        $vars = ['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_ENDPOINT', 'R2_REGION', 'R2_BUCKET', 'R2_PRIVATE_BUCKET', 'R2_PUBLIC_URL', 'APP_URL'];
        foreach ($vars as $v) {
            $val = (string) env($v);
            $show = $val === '' ? '<KOSONG>' : (str_contains($v, 'SECRET') || str_contains($v, 'KEY_ID') ? substr($val, 0, 4) . '***' : $val);
            $this->line(sprintf('   %-22s %s', $v, $show));
            if ($val === '' && $v !== 'R2_REGION') {
                $ok = $ok && ! in_array($v, ['R2_BUCKET', 'R2_PUBLIC_URL', 'R2_ENDPOINT', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY'], true);
            }
        }
        $publicUrl = (string) env('R2_PUBLIC_URL');
        if (str_contains($publicUrl, '<') || str_contains($publicUrl, '>')) {
            $this->error('   R2_PUBLIC_URL masih berisi placeholder (<PROJECT_REF>). Ganti dengan ID project asli.');
            $ok = false;
        }
        if ($publicUrl !== '' && str_ends_with($publicUrl, '/')) {
            $this->warn('   R2_PUBLIC_URL berakhir dengan "/". Hapus garis miring di akhir.');
        }

        $this->newLine();
        $this->info('2) Disk yang dipakai aplikasi');
        $driver = config('filesystems.disks.public.driver');
        $this->line('   public  → driver: ' . $driver . ($driver === 'local' ? '  (DISK LOKAL: foto hilang tiap deploy)' : ' / bucket: ' . config('filesystems.disks.public.bucket')));
        $this->line('   private → driver: ' . config('filesystems.disks.local.driver') . ($driver !== 'local' ? ' / bucket: ' . config('filesystems.disks.local.bucket') : ''));
        if ($driver === 'local') {
            $this->error('   R2_BUCKET kosong/tidak terbaca, jadi upload masih ke disk lokal. Isi R2_BUCKET di service Laravel, Deploy, lalu coba lagi.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('3) Uji tulis → baca URL publik → hapus');
        $path = 'healthcheck/media-check-' . now()->format('YmdHis') . '.txt';
        $disk = Storage::disk('public');

        try {
            $disk->put($path, 'ok');
            $this->line('   ✔ Upload ke bucket publik berhasil');
        } catch (Throwable $e) {
            $this->error('   ✘ Upload gagal: ' . $e->getMessage());
            $this->line('     Biasanya: access key/secret salah, endpoint/region salah, atau nama bucket salah.');

            return self::FAILURE;
        }

        $url = media_url($path);
        $this->line('   URL publik: ' . $url);
        try {
            $res = Http::timeout(15)->get($url);
            if ($res->successful() && trim($res->body()) === 'ok') {
                $this->line('   ✔ URL bisa dibuka dari luar (HTTP ' . $res->status() . ')');
            } else {
                $ok = false;
                $this->error('   ✘ URL tidak bisa dibuka (HTTP ' . $res->status() . ')');
                $this->line('     404/400 → R2_PUBLIC_URL salah (harus .../object/public/<nama-bucket-publik>) atau bucket belum Public.');
            }
        } catch (Throwable $e) {
            $ok = false;
            $this->error('   ✘ Gagal membuka URL: ' . $e->getMessage());
        }

        try {
            $disk->delete($path);
        } catch (Throwable) {
            // file uji kecil, abaikan
        }

        $this->newLine();
        $this->info('4) Uji bucket privat (bukti bayar & foto retur)');
        try {
            $priv = Storage::disk('local');
            $priv->put('healthcheck/private-check.txt', 'ok');
            $priv->delete('healthcheck/private-check.txt');
            $this->line('   ✔ Bucket privat bisa ditulis');
        } catch (Throwable $e) {
            $ok = false;
            $this->error('   ✘ Bucket privat gagal: ' . $e->getMessage());
        }

        $this->newLine();
        if ($ok) {
            $this->info('SEMUA OK. Upload ulang foto dari panel admin; foto lama yang hilang harus diunggah lagi.');

            return self::SUCCESS;
        }
        $this->error('Ada yang belum benar, lihat tanda ✘ di atas.');

        return self::FAILURE;
    }
}
