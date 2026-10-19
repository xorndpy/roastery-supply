<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Payments\MidtransService;
use DomainException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pembayaran online (Midtrans Snap) dari sisi pelanggan.
 *
 * Prinsip pencegahan bayar ganda dari sisi pelanggan:
 *  - Satu pesanan hanya punya SATU sesi pembayaran aktif. Klik "Bayar" lagi memakai sesi yang sama (token sama).
 *  - Bila bukti transfer manual sedang diverifikasi atau pembayaran sedang ditinjau, sesi baru ditolak.
 *  - Pembuatan sesi diserialkan per pesanan (kunci cache + kunci baris), jadi klik ganda/dua tab tidak membuat dua sesi.
 */
class OnlinePaymentController extends Controller
{
    public function __construct(private MidtransService $midtrans)
    {
    }

    public function start(string $orderNumber): JsonResponse
    {
        if (!$this->midtrans->configured()) {
            return response()->json(['message' => 'Pembayaran online belum tersedia. Silakan gunakan transfer manual.'], 503);
        }

        $order = $this->findOwn($orderNumber);

        $lock = Cache::lock('pay-start:' . $order->id, 25);
        try {
            $lock->block(10);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'Permintaan sebelumnya masih diproses. Coba lagi sebentar.'], 429);
        }

        try {
            $attempt = DB::transaction(function () use ($order) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($order->status !== 'menunggu_bayar') {
                    throw new DomainException('Pesanan ini sudah tidak menunggu pembayaran.');
                }
                if ($order->payments()->where('status', 'pending')->exists()) {
                    throw new DomainException('Bukti transfer Anda sedang diverifikasi admin. Tidak perlu membayar lagi.');
                }
                if ($order->payments()->where('status', 'review')->whereNull('reviewed_at')->exists()) {
                    throw new DomainException('Pembayaran Anda sedang kami tinjau. Mohon tunggu kabar dari kami; jangan membayar lagi.');
                }
                $deadline = $order->payment_deadline;
                if ($deadline->isPast()) {
                    throw new DomainException('Batas waktu pembayaran pesanan ini sudah lewat.');
                }

                // Pakai ulang sesi yang masih berlaku.
                $active = $order->payments()->where('provider', 'midtrans')->where('status', 'waiting')
                    ->where('expires_at', '>', now())->latest('id')->lockForUpdate()->first();
                if ($active) {
                    return $active;
                }

                $expires = now()->addMinutes(max(5, (int) config('services.midtrans.expiry_minutes', 1440)));
                if ($expires->gt($deadline)) {
                    $expires = $deadline->copy();
                }
                if ($expires->lt(now()->addMinutes(5))) {
                    throw new DomainException('Batas waktu pembayaran hampir habis. Silakan hubungi kami.');
                }

                // Nomor percobaan berurutan per pesanan; indeks UNIQUE provider_order_id jadi pengaman terakhir.
                $seq = $order->payments()->where('provider', 'midtrans')->count() + 1;

                return $order->payments()->create([
                    'method'            => 'midtrans',
                    'provider'          => 'midtrans',
                    'provider_order_id' => $order->order_number . '-P' . $seq,
                    'amount'            => $order->total,      // selalu dari database, tidak pernah dari request
                    'status'            => 'waiting',
                    'expires_at'        => $expires,
                ]);
            });

            if (!$attempt->snap_token) {
                try {
                    $snap = $this->midtrans->createSnap($order, $attempt);
                    $attempt->update(['snap_token' => $snap['token'], 'redirect_url' => $snap['redirect_url'] ?: null]);
                } catch (Throwable $e) {
                    report($e);
                    $attempt->update(['status' => 'rejected', 'note' => 'Gagal membuat sesi pembayaran di gateway.']);

                    return response()->json(['message' => 'Gagal menghubungi layanan pembayaran. Coba lagi sebentar atau gunakan transfer manual.'], 502);
                }
            }

            return response()->json([
                'token'        => $attempt->snap_token,
                'redirect_url' => $attempt->redirect_url,
                'expires_at'   => $attempt->expires_at->toIso8601String(),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Status ringkas untuk polling halaman pesanan. Hanya membaca database (tidak memanggil gateway).
     */
    public function status(string $orderNumber): JsonResponse
    {
        $order = $this->findOwn($orderNumber);
        $payment = $order->payments()->whereNotNull('provider')->latest('id')->first();

        [$state, $message] = $this->describe($order, $payment);

        return response()->json([
            'order_status' => $order->status,
            'order_label'  => $order->status_label,
            'state'        => $state,
            'message'      => $message,
            'done'         => $state !== 'waiting',
            'confirmed'    => $order->status !== 'menunggu_bayar' && $order->status !== 'batal',
        ]);
    }

    /** @return array{0: string, 1: string} [state, pesan untuk pelanggan] */
    private function describe(Order $order, ?Payment $payment): array
    {
        if (!in_array($order->status, ['menunggu_bayar', 'batal'], true)) {
            return ['paid', 'Pembayaran diterima. Pesanan Anda sudah dikonfirmasi.'];
        }
        if ($order->status === 'batal') {
            return ['cancelled', 'Pesanan ini dibatalkan.'];
        }
        if (!$payment) {
            return ['none', ''];
        }

        return match ($payment->status) {
            'waiting'   => ['waiting', 'Menunggu konfirmasi pembayaran. Anda tidak perlu membayar lagi.'],
            'review'    => ['review', $this->reviewMessage($payment->review_reason)],
            'duplicate' => ['review', 'Pembayaran ganda terdeteksi. Pesanan Anda tidak terpengaruh, dan kelebihan pembayaran akan dikembalikan.'],
            'expired'   => ['expired', 'Sesi pembayaran sudah berakhir. Anda bisa memulai pembayaran lagi.'],
            'rejected'  => ['failed', 'Pembayaran tidak berhasil. Anda bisa mencoba lagi dengan metode lain.'],
            default     => ['none', ''],
        };
    }

    private function reviewMessage(?string $reason): string
    {
        return match ($reason) {
            'stok_kurang'         => 'Pembayaran Anda sudah kami terima, namun stok sedang tidak mencukupi. Tim kami akan menghubungi Anda untuk langkah berikutnya (pengembalian dana atau menunggu stok).',
            'nominal_tidak_cocok' => 'Pembayaran Anda sudah kami terima, tetapi nominalnya berbeda dari tagihan. Tim kami sedang memeriksa dan akan menghubungi Anda.',
            'fraud_challenge'     => 'Pembayaran kartu Anda sedang menjalani pemeriksaan keamanan. Mohon tunggu.',
            default               => 'Pembayaran Anda sudah kami terima dan sedang ditinjau oleh tim kami. Anda tidak perlu membayar lagi.',
        };
    }

    private function findOwn(string $orderNumber): Order
    {
        return Order::where('order_number', $orderNumber)->where('user_id', auth()->id())->firstOrFail();
    }
}
