<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Notifications\OrderStatusChanged;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Satu-satunya tempat yang boleh mengubah status order dan stok.
 * Sebelumnya logika ini terduplikasi di Admin\OrderController, Admin\PaymentController
 * dan model Order dengan perilaku yang berbeda-beda.
 *
 * Aturan (PRD bagian 5, 6.2, 11):
 *  - Stok berkurang saat `dikonfirmasi`, bukan saat checkout.
 *  - Status hanya maju; satu-satunya jalan mundur adalah `batal`.
 *  - Batal setelah dikonfirmasi mengembalikan stok.
 *  - Tidak cukup stok saat konfirmasi -> konfirmasi ditolak.
 *  - Order menunggu_bayar > 24 jam otomatis dibatalkan.
 */
class OrderService
{
    public const PAYMENT_WINDOW_HOURS = 24;

    /** Status yang sudah memotong stok. */
    private const STOCK_DEDUCTED = ['dikonfirmasi', 'diproses'];

    /**
     * Verifikasi pembayaran -> dikonfirmasi + kurangi stok.
     * Semua atau tidak sama sekali (satu transaksi, baris dikunci).
     */
    public function confirmPayment(Order $order, ?int $actorId = null, ?Payment $payment = null): Order
    {
        $order = DB::transaction(function () use ($order, $actorId, $payment) {
            $order = $this->lock($order);
            $this->assertStatus($order, ['menunggu_bayar'], 'Pesanan tidak dalam status menunggu pembayaran.');

            $query = $order->payments()->where('status', 'pending');
            if ($payment) {
                $query->whereKey($payment->id);
            }
            $pending = $query->latest('id')->lockForUpdate()->first();

            if (!$pending) {
                throw new DomainException('Belum ada bukti pembayaran yang menunggu verifikasi.');
            }

            $items = $order->items()->get();

            // Kunci baris produk berurutan (id ASC) agar tidak deadlock antar-order.
            $products = Product::whereIn('id', $items->pluck('product_id')->filter())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item->product_id);

                if (!$product) {
                    throw new DomainException("Produk \"{$item->product_name}\" sudah tidak tersedia. Batalkan pesanan atau hubungi customer.");
                }
                if ($product->stock < $item->quantity) {
                    throw new DomainException("Stok \"{$product->name}\" tidak mencukupi (sisa {$product->stock}, dipesan {$item->quantity}). Cek stok dulu.");
                }
            }

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $product->decrement('stock', $item->quantity);

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'out',
                    'quantity'       => $item->quantity,
                    'reference_type' => 'order',
                    'reference_id'   => $order->id,
                    'note'           => 'Pesanan ' . $order->order_number,
                    'user_id'        => $actorId,
                ]);
            }

            $pending->update([
                'status'      => 'verified',
                'verified_by' => $actorId,
                'verified_at' => now(),
            ]);

            $order->update(['status' => 'dikonfirmasi']);

            return $order;
        });

        $this->notify($order, 'dikonfirmasi');

        return $order;
    }

    /** Tolak bukti bayar. Order tetap menunggu_bayar, customer boleh unggah ulang. */
    public function rejectPayment(Payment $payment, ?string $note = null, ?int $actorId = null): Payment
    {
        $payment = DB::transaction(function () use ($payment, $note, $actorId) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                throw new DomainException('Pembayaran ini sudah diproses sebelumnya.');
            }

            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($order, ['menunggu_bayar'], 'Order tidak dalam status menunggu pembayaran.');

            $payment->update([
                'status'      => 'rejected',
                'verified_by' => $actorId,
                'verified_at' => now(),
                'note'        => $note ?: 'Ditolak oleh admin.',
            ]);

            return $payment;
        });

        $this->notify($payment->order, 'payment_rejected', $payment->note);

        return $payment;
    }

    public function process(Order $order): Order
    {
        $order = $this->transition($order, ['dikonfirmasi'], 'diproses', 'Pesanan harus dalam status dikonfirmasi.');
        $this->notify($order, 'diproses');

        return $order;
    }

    /** @param array{courier:string, service?:?string, tracking_number:string, cost?:mixed} $data */
    public function ship(Order $order, array $data): Order
    {
        $order = DB::transaction(function () use ($order, $data) {
            $order = $this->lock($order);
            $this->assertStatus($order, ['dikonfirmasi', 'diproses'], 'Pesanan belum siap dikirim.');

            Shipment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'courier'         => $data['courier'],
                    'service'         => $data['service'] ?? null,
                    'tracking_number' => $data['tracking_number'],
                    'cost'            => $data['cost'] ?? 0,
                    'status'          => 'shipped',
                    'shipped_at'      => now(),
                ]
            );

            $order->update(['status' => 'dikirim']);

            return $order;
        });

        $this->notify($order, 'dikirim', "Kurir {$data['courier']}, resi {$data['tracking_number']}.");

        return $order;
    }

    public function complete(Order $order): Order
    {
        $order = DB::transaction(function () use ($order) {
            $order = $this->lock($order);
            $this->assertStatus($order, ['dikirim'], 'Pesanan harus dalam status dikirim.');

            $order->update(['status' => 'selesai', 'completed_at' => now()]);
            $order->shipments()->update(['status' => 'delivered', 'delivered_at' => now()]);

            return $order;
        });

        $this->notify($order, 'selesai');

        return $order;
    }

    /**
     * Batalkan order. Stok dikembalikan hanya jika sudah sempat dipotong.
     *
     * @param array<int,string> $allowedFrom status asal yang boleh dibatalkan
     *        (customer: hanya menunggu_bayar; admin: sampai diproses).
     */
    public function cancel(
        Order $order,
        ?string $reason = null,
        ?int $actorId = null,
        array $allowedFrom = ['menunggu_bayar', 'dikonfirmasi', 'diproses'],
    ): Order {
        $order = DB::transaction(function () use ($order, $reason, $actorId, $allowedFrom) {
            $order = $this->lock($order);
            $this->assertStatus($order, $allowedFrom, 'Pesanan pada status ini tidak bisa dibatalkan.');

            if (in_array($order->status, self::STOCK_DEDUCTED, true)) {
                $items = $order->items()->get();
                $products = Product::withTrashed()
                    ->whereIn('id', $items->pluck('product_id')->filter())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    if (!$product) {
                        continue;
                    }

                    $product->increment('stock', $item->quantity);

                    StockMovement::create([
                        'product_id'     => $product->id,
                        'type'           => 'in',
                        'quantity'       => $item->quantity,
                        'reference_type' => 'order_cancel',
                        'reference_id'   => $order->id,
                        'note'           => 'Pembatalan ' . $order->order_number,
                        'user_id'        => $actorId,
                    ]);
                }
            }

            // Kuota voucher dikembalikan (order batal tidak lagi memakai voucher).
            app(VoucherService::class)->release($order);

            // Bukti bayar yang masih menunggu ikut ditutup supaya tidak muncul di antrean verifikasi.
            $order->payments()->where('status', 'pending')->update([
                'status' => 'rejected',
                'note'   => 'Order dibatalkan.',
            ]);

            $order->update([
                'status' => 'batal',
                'note'   => $reason ? trim($order->note . "\nBatal: " . $reason) : $order->note,
            ]);

            return $order;
        });

        $this->notify($order, 'batal', $reason);

        return $order;
    }

    /** Auto-cancel order menunggu_bayar yang lewat 24 jam (PRD aturan #6). */
    public function cancelExpired(): int
    {
        $cancelled = 0;

        $expired = Order::where('status', 'menunggu_bayar')
            ->where('created_at', '<=', now()->subHours(self::PAYMENT_WINDOW_HOURS))
            // Jangan batalkan kalau ada bukti bayar yang sedang menunggu dicek admin.
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'pending'))
            ->pluck('id');

        foreach ($expired as $id) {
            try {
                $this->cancel(Order::findOrFail($id), 'Pembayaran melewati batas 24 jam.', null, ['menunggu_bayar']);
                $cancelled++;
            } catch (DomainException) {
                // Status berubah di antara query dan lock (mis. baru diunggah/dibayar) -> lewati.
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $cancelled;
    }

    // ------------------------------------------------------------------

    private function transition(Order $order, array $from, string $to, string $message): Order
    {
        return DB::transaction(function () use ($order, $from, $to, $message) {
            $order = $this->lock($order);
            $this->assertStatus($order, $from, $message);
            $order->update(['status' => $to]);

            return $order;
        });
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    private function assertStatus(Order $order, array $allowed, string $message): void
    {
        if (!in_array($order->status, $allowed, true)) {
            throw new DomainException($message);
        }
    }

    private function notify(?Order $order, string $event, ?string $extra = null): void
    {
        $user = $order?->user;
        if (!$user) {
            return;
        }

        try {
            $user->notify(new OrderStatusChanged($order, $event, $extra));
        } catch (Throwable $e) {
            report($e); // email gagal tidak boleh membatalkan perubahan status
        }
    }
}
