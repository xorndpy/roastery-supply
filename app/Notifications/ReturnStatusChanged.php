<?php

namespace App\Notifications;

use App\Models\ReturnOrder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReturnStatusChanged extends Notification
{
    public function __construct(public ReturnOrder $return, public string $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->return;
        $order = $r->order;

        [$subject, $lines] = match ($this->event) {
            'approved' => ['Pengajuan retur disetujui', [
                'Pengajuan retur kamu disetujui.',
                'Refund sebesar Rp ' . number_format($r->refund_amount, 0, ',', '.') . ' akan ditransfer ke rekening yang kamu cantumkan.',
            ]],
            'rejected' => ['Pengajuan retur ditolak', array_filter(['Pengajuan retur kamu belum dapat kami setujui.', $r->note ? 'Alasan: ' . $r->note : null])],
            'refunded' => ['Refund sudah ditransfer', ['Refund sebesar Rp ' . number_format($r->refund_amount, 0, ',', '.') . ' sudah kami transfer ke rekening kamu.']],
            default    => ['Update retur', ['Status retur kamu berubah.']],
        };

        $mail = (new MailMessage)->subject("[{$order->order_number}] {$subject}")->greeting('Halo ' . ($notifiable->name ?? '') . ',');
        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail->line('Nomor pesanan: ' . $order->order_number)->action('Lihat retur', route('customer.returns.show', $r->id));
    }
}
