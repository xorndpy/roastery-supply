<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ke pelanggan saat status tiket servis berubah. Tanpa queue (jalan tanpa worker). */
class ServiceTicketUpdated extends Notification
{
    public function __construct(public ServiceTicket $ticket, public ?string $note = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->ticket;
        $mail = (new MailMessage)
            ->subject("[{$t->ticket_number}] Status servis: {$t->statusLabel()}")
            ->greeting('Halo ' . ($notifiable->name ?? '') . ',')
            ->line("Tiket servis {$t->ticket_number} ({$t->product_name}) sekarang berstatus: {$t->statusLabel()}.");

        if ($this->note) {
            $mail->line('Catatan teknisi: ' . $this->note);
        }
        if ($t->status === 'menunggu_persetujuan' && $t->estimate_cost) {
            $mail->line('Estimasi biaya perbaikan: Rp ' . number_format($t->estimate_cost, 0, ',', '.') . '. Mohon setujui atau tolak di halaman tiket.');
        }
        if ($t->status === 'selesai' && (float) $t->final_cost > 0) {
            $mail->line('Biaya servis: Rp ' . number_format($t->final_cost, 0, ',', '.') . '.');
        }

        return $mail->action('Lihat tiket servis', route('customer.service.show', $t->ticket_number));
    }
}
