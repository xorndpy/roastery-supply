<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan kesiapan produksi: php artisan app:preflight
 * Keluar dengan kode 1 bila ada yang GAGAL (mis. APP_DEBUG menyala di produksi).
 */
class Preflight extends Command
{
    protected $signature = 'app:preflight';

    protected $description = 'Periksa pengaturan keamanan dan operasional sebelum/sesudah rilis ke produksi';

    /** @var array<int, array{0:string,1:string,2:string}> */
    private array $rows = [];

    public function handle(): int
    {
        $prod = app()->environment('production');

        $this->check($prod, 'APP_ENV = production', 'WARN', 'APP_ENV saat ini "' . app()->environment() . '". Di server live harus production.');
        $this->check(! config('app.debug'), 'APP_DEBUG = false', 'FAIL', 'Mode debug membuka jejak kode, path server, bahkan isi .env ke pengunjung.');
        $this->check(filled(config('app.key')), 'APP_KEY terisi', 'FAIL', 'Jalankan php artisan key:generate.');
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL memakai https', 'WARN', 'APP_URL = "' . config('app.url') . '".');
        $this->check(config('session.secure') === true, 'SESSION_SECURE_COOKIE = true', 'WARN', 'Cookie sesi sebaiknya hanya dikirim lewat HTTPS.');
        $level = config('logging.channels.single.level') ?? config('logging.channels.daily.level') ?? 'debug';
        $this->check(! $prod || $level !== 'debug', 'LOG_LEVEL bukan debug di produksi', 'WARN', 'Log level debug memperbesar log dan bisa memuat data sensitif.');

        try {
            DB::connection()->getPdo();
            $this->check(true, 'Koneksi database', 'FAIL', '');
        } catch (\Throwable $e) {
            $this->check(false, 'Koneksi database', 'FAIL', 'Tidak bisa tersambung: ' . $e->getMessage());
        }

        $this->check(! in_array(config('mail.default'), ['log', 'array'], true), 'Email terkirim sungguhan (MAIL_MAILER)', 'WARN', 'MAIL_MAILER = "' . config('mail.default') . '": email tidak benar-benar dikirim.');

        $siteKey = filled(config('services.turnstile.site_key'));
        $secret = filled(config('services.turnstile.secret_key'));
        $this->check($siteKey === $secret, 'Kunci Turnstile lengkap (site key dan secret key)', 'WARN', 'Hanya salah satu yang terisi, sehingga verifikasi tidak akan jalan.');

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            $this->check(false, 'Backup database harian', 'WARN', 'Folder storage/app/backups belum ada. Lihat docs/OPERASIONAL.md.');
        } else {
            $newest = collect(glob($dir . '/*') ?: [])->map(fn ($f) => filemtime($f))->max();
            $this->check($newest && $newest > now()->subHours(48)->timestamp, 'Backup database < 48 jam', 'WARN', $newest ? 'Backup terakhir ' . now()->createFromTimestamp($newest)->diffForHumans() . '.' : 'Folder backup kosong.');
        }

        $this->table(['Status', 'Pemeriksaan', 'Catatan'], $this->rows);

        $fail = collect($this->rows)->where(0, 'GAGAL')->count();
        $warn = collect($this->rows)->where(0, 'PERINGATAN')->count();
        $this->line('');
        $fail ? $this->error("{$fail} gagal, {$warn} peringatan.") : $this->info("Tidak ada yang gagal. {$warn} peringatan.");

        return $fail ? self::FAILURE : self::SUCCESS;
    }

    private function check(bool $ok, string $label, string $level, string $hint): void
    {
        $this->rows[] = [$ok ? 'OK' : ($level === 'FAIL' ? 'GAGAL' : 'PERINGATAN'), $label, $ok ? '' : $hint];
    }
}
