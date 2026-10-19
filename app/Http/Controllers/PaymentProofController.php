<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

/**
 * Bukti transfer berisi data keuangan, jadi TIDAK disimpan di disk publik.
 * File hanya bisa dibuka lewat controller ini: pemilik order, atau admin/staff berizin verify_payments.
 */
class PaymentProofController extends Controller
{
    /** Admin/staff: route sudah dijaga middleware can:verify_payments. */
    public function admin(Payment $payment)
    {
        return $this->serve($payment);
    }

    /** Customer: hanya bukti terbaru dari order miliknya sendiri. */
    public function customer(string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return $this->serve($order->payments()->whereNotNull('proof_image')->latest('id')->firstOrFail());
    }

    private function serve(Payment $payment)
    {
        $path = $payment->proof_image;
        abort_unless($path, 404);

        // 'local' = privat (baru). 'public' = file lama sebelum migrasi (lihat: php artisan payments:privatize).
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, null, [
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        abort(404);
    }
}
