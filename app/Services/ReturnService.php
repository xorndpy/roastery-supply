<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\ReturnStatusChanged;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Aturan retur (PRD: maks 7 hari setelah selesai, approve manual admin, satu retur per order):
 *  - hanya order `selesai`, dan pengajuan diterima selama 7 hari sejak selesai
 *  - satu pengajuan per order; wajib alasan, foto bukti, dan rekening refund
 *  - admin menyetujui (atur nominal refund <= total order, opsional kembalikan stok) atau menolak (alasan wajib)
 *  - refund ditransfer manual lalu ditandai selesai
 */
class ReturnService
{
    public const WINDOW_DAYS = 7;

    /** null = boleh diajukan; selain itu pesan alasan penolakan. */
    public function ineligibleReason(Order $order): ?string
    {
        if ($order->status !== 'selesai') {
            return 'Retur hanya bisa diajukan untuk pesanan yang sudah selesai.';
        }
        if (!$order->completed_at) {
            return 'Waktu penyelesaian pesanan tidak tercatat. Silakan hubungi kami.';
        }
        if (now()->greaterThan($order->return_deadline)) {
            return 'Batas pengajuan retur ' . self::WINDOW_DAYS . ' hari sejak pesanan selesai sudah lewat.';
        }
        if ($order->returns()->withTrashed()->exists()) {
            return 'Pesanan ini sudah pernah diajukan retur.';
        }

        return null;
    }

    /**
     * @param array{reason_type:string, reason:string, refund_bank:string, refund_account_number:string, refund_account_name:string} $data
     * @param array<int,string> $evidencePaths path foto yang sudah tersimpan di disk privat
     */
    public function submit(Order $order, array $data, array $evidencePaths): ReturnOrder
    {
        $return = DB::transaction(function () use ($order, $data, $evidencePaths) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($reason = $this->ineligibleReason($order)) {
                throw new DomainException($reason);
            }

            try {
                return ReturnOrder::create($data + [
                    'order_id'      => $order->id,
                    'status'        => 'pending',
                    'evidence'      => array_values($evidencePaths),
                    'refund_amount' => $order->total,
                ]);
            } catch (QueryException) {
                // kunci unik order_id: klik ganda / dua tab
                throw new DomainException('Pesanan ini sudah pernah diajukan retur.');
            }
        });

        return $return;
    }

    /**
     * Setujui retur. $refundAmount null = sebesar total pesanan. $restock = barang layak jual -> stok bertambah.
     */
    public function approve(ReturnOrder $return, int $actorId, ?float $refundAmount, bool $restock, ?string $note): ReturnOrder
    {
        $return = DB::transaction(function () use ($return, $actorId, $refundAmount, $restock, $note) {
            $return = ReturnOrder::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($return, 'pending', 'Retur ini sudah diproses.');

            $order = Order::whereKey($return->order_id)->lockForUpdate()->firstOrFail();
            $refund = $refundAmount ?? (float) $order->total;

            if ($refund < 0 || $refund > (float) $order->total) {
                throw new DomainException('Nominal refund tidak boleh melebihi total pesanan (Rp ' . number_format($order->total, 0, ',', '.') . ').');
            }

            if ($restock) {
                $items = $order->items()->get();
                $products = Product::whereIn('id', $items->pluck('product_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

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
                        'reference_type' => 'return',
                        'reference_id'   => $return->id,
                        'note'           => 'Retur ' . $order->order_number,
                        'user_id'        => $actorId,
                    ]);
                }
            }

            $return->update([
                'status'        => 'approved',
                'refund_amount' => $refund,
                'restocked'     => $restock,
                'approved_by'   => $actorId,
                'approved_at'   => now(),
                'note'          => $note,
            ]);

            return $return;
        });

        $this->notify($return, 'approved');

        return $return;
    }

    public function reject(ReturnOrder $return, int $actorId, string $note): ReturnOrder
    {
        $return = DB::transaction(function () use ($return, $actorId, $note) {
            $return = ReturnOrder::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($return, 'pending', 'Retur ini sudah diproses.');

            $return->update([
                'status'        => 'rejected',
                'refund_amount' => 0,
                'approved_by'   => $actorId,
                'approved_at'   => now(),
                'note'          => $note,
            ]);

            return $return;
        });

        $this->notify($return, 'rejected');

        return $return;
    }

    /** Tandai dana sudah ditransfer ke customer. */
    public function markRefunded(ReturnOrder $return): ReturnOrder
    {
        $return = DB::transaction(function () use ($return) {
            $return = ReturnOrder::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($return, 'approved', 'Hanya retur yang disetujui yang bisa ditandai refund.');

            if ($return->refunded_at) {
                throw new DomainException('Refund sudah ditandai selesai.');
            }

            $return->update(['refunded_at' => now()]);

            return $return;
        });

        $this->notify($return, 'refunded');

        return $return;
    }

    private function assertStatus(ReturnOrder $return, string $expected, string $message): void
    {
        if ($return->status !== $expected) {
            throw new DomainException($message);
        }
    }

    private function notify(ReturnOrder $return, string $event): void
    {
        $user = $return->order?->user;
        if (!$user) {
            return;
        }

        try {
            $user->notify(new ReturnStatusChanged($return->load('order'), $event));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
