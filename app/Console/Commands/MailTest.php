<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTest extends Command
{
    protected $signature = 'mail:test {email : Alamat tujuan email uji}';

    protected $description = 'Kirim email uji untuk memastikan pengaturan SMTP (MAIL_*) di .env sudah benar.';

    public function handle(): int
    {
        $to = $this->argument('email');
        $this->line('Mailer : ' . config('mail.default'));
        $this->line('Host   : ' . config('mail.mailers.' . config('mail.default') . '.host', '-') . ':' . config('mail.mailers.' . config('mail.default') . '.port', '-'));
        $this->line('Dari   : ' . config('mail.from.address'));

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER=log: email hanya ditulis ke storage/logs/laravel.log, tidak benar-benar terkirim. Ubah ke smtp di .env.');
        }

        try {
            Mail::raw('Ini email uji dari ' . config('app.name') . '. Kalau Anda menerimanya, pengaturan email sudah benar.', function ($m) use ($to) {
                $m->to($to)->subject('Tes email ' . config('app.name'));
            });
        } catch (Throwable $e) {
            $this->error('Gagal mengirim: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Terkirim ke {$to}. Cek kotak masuk dan folder spam.");

        return self::SUCCESS;
    }
}
