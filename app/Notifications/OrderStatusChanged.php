<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email tiap perubahan status order (PRD 6.2).
 * Sengaja tidak di-queue supaya jalan tanpa queue worker; kegagalan kirim
 * ditangani di OrderService agar tidak menggagalkan transaksi.
 */
class OrderStatusChanged extends Notification
{
    public function __construct(
        public Order $order,
        public string $event,
        public ?string $extra = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;

        [$subject, $line] = match ($this->event) {
            'dikonfirmasi'     => ['Pembayaran dikonfirmasi', 'Pembayaran kamu sudah kami verifikasi. Pesanan akan segera kami proses.'],
            'diproses'         => ['Pesanan sedang diproses', 'Pesanan kamu sedang kami siapkan.'],
            'dikirim'          => ['Pesanan dikirim', 'Pesanan kamu sudah dikirim.' . ($this->extra ? " {$this->extra}" : '')],
            'selesai'          => ['Pesanan selesai', 'Pesanan kamu sudah selesai. Terima kasih sudah berbelanja.'],
            'batal'            => ['Pesanan dibatalkan', 'Pesanan kamu dibatalkan.' . ($this->extra ? " Alasan: {$this->extra}" : '')],
            'payment_rejected' => ['Bukti pembayaran ditolak', 'Bukti pembayaran belum bisa kami terima.' . ($this->extra ? " Catatan: {$this->extra}" : '') . ' Silakan unggah ulang.'],
            default            => ['Update pesanan', 'Status pesanan kamu berubah.'],
        };

        return (new MailMessage)
            ->subject("[{$order->order_number}] {$subject}")
            ->greeting('Halo ' . ($notifiable->name ?? '') . ',')
            ->line($line)
            ->line('Nomor pesanan: ' . $order->order_number)
            ->action('Lihat pesanan', route('customer.orders.show', $order->order_number));
    }
}
