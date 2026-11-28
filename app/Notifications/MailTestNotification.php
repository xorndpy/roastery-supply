<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email uji untuk memastikan SMTP dan tampilan email berfungsi: php artisan mail:test alamat@email */
class MailTestNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = Setting::get('site_name', config('app.name'));

        return (new MailMessage)
            ->subject("Uji pengiriman email {$site}")
            ->greeting('Halo,')
            ->line("Jika Anda membaca email ini, pengiriman email dari {$site} sudah berfungsi.")
            ->line('Dikirim dari ' . config('mail.from.address') . ' lewat mailer "' . config('mail.default') . '".')
            ->action('Buka situs', url('/'))
            ->line('Periksa juga apakah email ini masuk ke kotak masuk, bukan folder spam.');
    }
}
