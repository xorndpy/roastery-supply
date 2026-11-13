<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email ke toko (alamat di Pengaturan > Kontak > Email Notifikasi Pesanan).
 * event 'baru'   = pesanan baru masuk (belum tentu dibayar, jadi tetap muncul walau Midtrans masih sandbox)
 * event 'dibayar' = pembayaran terkonfirmasi, siap diproses.
 */
class NewOrderAlert extends Notification
{
    public function __construct(public Order $order, public string $event = 'baru')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items');
        $total = 'Rp ' . number_format($order->total, 0, ',', '.');
        $paid = $this->event === 'dibayar';

        $mail = (new MailMessage)
            ->subject(($paid ? '[Pembayaran masuk] ' : '[Pesanan baru] ') . "{$order->order_number} · {$total}")
            ->greeting($paid ? 'Pembayaran sudah dikonfirmasi' : 'Ada pesanan baru')
            ->line($paid ? 'Pesanan ini sudah dibayar dan siap diproses.' : 'Pesanan masuk dan menunggu pembayaran.')
            ->line('Nomor pesanan: ' . $order->order_number)
            ->line('Pelanggan: ' . ($order->shipping_name ?: ($order->user->name ?? '-')) . ($order->shipping_phone ? ' (' . $order->shipping_phone . ')' : ''));

        foreach ($order->items->take(10) as $item) {
            $mail->line('• ' . $item->product_name . ' × ' . $item->quantity);
        }
        if ($order->items->count() > 10) {
            $mail->line('dan ' . ($order->items->count() - 10) . ' produk lainnya.');
        }

        return $mail
            ->line('Total: ' . $total)
            ->line('Kirim ke: ' . ($order->full_shipping_address ?: '-'))
            ->action('Buka di panel admin', route('admin.orders.show', $order->id));
    }
}
