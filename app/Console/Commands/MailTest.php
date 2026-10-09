<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : Alamat tujuan}';

    protected $description = 'Kirim email percobaan untuk memastikan pengaturan SMTP benar.';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $this->line("Mailer: {$mailer}" . ($mailer === 'smtp' ? ' (' . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port') . ')' : ''));

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER={$mailer}: email TIDAK dikirim sungguhan (hanya ditulis ke storage/logs). Ubah ke smtp di .env.");
        }

        try {
            Mail::raw('Tes email dari ' . config('app.name') . '. Kalau kamu menerima ini, SMTP sudah benar.', function ($m) {
                $m->to($this->argument('email'))->subject('Tes SMTP ' . config('app.name'));
            });
        } catch (Throwable $e) {
            $this->error('Gagal: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Terkirim ke ' . $this->argument('email') . '.');

        return self::SUCCESS;
    }
}
