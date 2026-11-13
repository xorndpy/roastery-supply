<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email ke pembeli saat pesanan baru dibuat (sebelum dibayar), jadi tidak bergantung pada Midtrans.
 * Sengaja tidak di-queue supaya jalan tanpa queue worker; kegagalan kirim ditangani di OrderService.
 */
class OrderPlaced extends Notification
{
    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items');
        $mail = (new MailMessage)
            ->subject("[{$order->order_number}] Pesanan diterima")
            ->greeting('Halo ' . ($notifiable->name ?? '') . ',')
            ->line('Terima kasih, pesanan kamu sudah kami terima.')
            ->line('Nomor pesanan: ' . $order->order_number);

        foreach ($order->items->take(10) as $item) {
            $mail->line('• ' . $item->product_name . ' × ' . $item->quantity);
        }
        if ($order->items->count() > 10) {
            $mail->line('dan ' . ($order->items->count() - 10) . ' produk lainnya.');
        }

        $mail->line('Total: Rp ' . number_format($order->total, 0, ',', '.'));

        if ($order->payment_deadline) {
            $mail->line('Mohon selesaikan pembayaran sebelum ' . wib($order->payment_deadline, 'd M Y, H:i', true) . '.');
        }

        return $mail->action('Lihat pesanan', route('customer.orders.show', $order->order_number));
    }
}
