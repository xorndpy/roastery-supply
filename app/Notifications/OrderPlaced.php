<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Setting;
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
        $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        // Tanda | dan baris baru dibuang dari nama produk supaya tabel markdown tidak rusak.
        $clean = fn ($t) => trim(str_replace(['|', "\r", "\n"], ['/', ' ', ' '], (string) $t));

        $items = $order->items->map(fn ($i) => [
            'name' => $clean($i->product_name),
            'qty' => (int) $i->quantity,
            'subtotal' => $rp($i->subtotal),
        ])->all();

        $totals = [['Subtotal', $rp($order->subtotal)]];
        if ((float) $order->discount > 0) {
            $totals[] = ['Diskon', '- ' . $rp($order->discount)];
        }
        $totals[] = ['Ongkos kirim' . ($order->shipping_method ? ' (' . $clean($order->shipping_method) . ')' : ''), $rp($order->shipping_cost)];
        $totals[] = ['**Total**', '**' . $rp($order->total) . '**'];
        if ($order->ppn_rate) {
            $rate = rtrim(rtrim(number_format($order->ppn_rate, 2, ',', ''), '0'), ',');
            $totals[] = ["Sudah termasuk PPN {$rate}%", $rp($order->ppn_amount)];
        }

        return (new MailMessage)
            ->subject("[{$order->order_number}] Pesanan diterima")
            ->markdown('emails.orders.placed', [
                'order' => $order,
                'name' => $notifiable->name ?? '',
                'siteName' => Setting::get('site_name', config('app.name')),
                'items' => $items,
                'totals' => $totals,
                'deadline' => $order->payment_deadline ? wib($order->payment_deadline, 'd M Y, H:i', true) : null,
                'recipient' => $clean($order->shipping_name) . ($order->shipping_phone ? ' (' . $clean($order->shipping_phone) . ')' : ''),
                'address' => $clean($order->full_shipping_address),
                'url' => route('customer.orders.show', $order->order_number),
                'total' => $rp($order->total),
            ]);
    }
}
