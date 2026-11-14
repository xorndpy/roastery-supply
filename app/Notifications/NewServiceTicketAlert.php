<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ke toko (alamat di Pengaturan > Kontak > Email Notifikasi Pesanan) saat ada tiket servis baru. */
class NewServiceTicketAlert extends Notification
{
    public function __construct(public ServiceTicket $ticket)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->ticket;

        return (new MailMessage)
            ->subject("[Tiket servis baru] {$t->ticket_number} · {$t->product_name}")
            ->greeting('Ada tiket servis baru')
            ->line('Pelanggan: ' . ($t->user->name ?? '-') . ' (' . $t->contact_phone . ')')
            ->line("Produk: {$t->product_name}" . ($t->serial_number ? " · SN {$t->serial_number}" : ''))
            ->line('Masalah: ' . $t->issueLabel())
            ->line('Status garansi: ' . ($t->in_warranty ? 'MASIH GARANSI' : 'di luar garansi / tanpa data garansi'))
            ->action('Buka di panel admin', route('admin.service.show', $t->id));
    }
}
