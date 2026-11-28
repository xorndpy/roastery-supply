<?php

namespace App\Console\Commands;

use App\Notifications\MailTestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class MailTest extends Command
{
    protected $signature = 'mail:test {to : Alamat email tujuan uji}';

    protected $description = 'Kirim email uji bertema KOFFLINE untuk memeriksa SMTP dan deliverability';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("\"{$to}\" bukan alamat email yang valid.");

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $this->line('Mailer : ' . $mailer);
        $this->line('Host   : ' . (config("mail.mailers.{$mailer}.host") ?: '-') . ':' . (config("mail.mailers.{$mailer}.port") ?: '-'));
        $this->line('Dari   : ' . config('mail.from.name') . ' <' . config('mail.from.address') . '>');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn('MAIL_MAILER = ' . $mailer . ': email hanya ditulis ke log, tidak benar-benar terkirim.');
        }
        if (str_ends_with((string) config('mail.from.address'), '@example.com')) {
            $this->warn('MAIL_FROM_ADDRESS masih contoh. Pakai alamat di domain Anda sendiri agar tidak dianggap spam.');
        }

        try {
            Notification::route('mail', $to)->notify(new MailTestNotification);
        } catch (\Throwable $e) {
            $this->error('Gagal mengirim: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Email uji dikirim ke {$to}. Cek kotak masuk dan folder spam.");

        return self::SUCCESS;
    }
}
