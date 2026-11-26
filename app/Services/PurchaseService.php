<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Pembelian dari supplier. Saat barang diterima: stok bertambah, HPP produk dihitung ulang
 * (rata-rata tertimbang, termasuk ongkir/bea pembelian yang dibagi rata menurut nilai barang),
 * dan satu baris mutasi stok bertipe "in" dicatat.
 */
class PurchaseService
{
    /** @param array<int,array{product_id:int,qty:int,unit_cost:float|int|string}> $items */
    public function create(array $data, array $items, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrder::generateNumber(),
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'expected_at' => $data['expected_at'] ?? null,
                'shipping_cost' => (float) ($data['shipping_cost'] ?? 0),
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);
            $this->syncItems($po, $items);

            return $po;
        });
    }

    public function update(PurchaseOrder $po, array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $items) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'draft') {
                throw new DomainException('Hanya pembelian berstatus Draft yang bisa diubah.');
            }
            $po->update([
                'supplier_id' => $data['supplier_id'],
                'expected_at' => $data['expected_at'] ?? null,
                'shipping_cost' => (float) ($data['shipping_cost'] ?? 0),
                'note' => $data['note'] ?? null,
            ]);
            $this->syncItems($po, $items);

            return $po;
        });
    }

    private function syncItems(PurchaseOrder $po, array $items): void
    {
        $po->items()->delete();
        $subtotal = 0.0;
        $merged = [];
        // Produk yang sama di dua baris digabung supaya satu produk = satu baris.
        foreach ($items as $row) {
            $pid = (int) $row['product_id'];
            $qty = (int) $row['qty'];
            $cost = (float) $row['unit_cost'];
            if ($qty < 1) {
                continue;
            }
            if (isset($merged[$pid])) {
                $total = $merged[$pid]['qty'] * $merged[$pid]['cost'] + $qty * $cost;
                $merged[$pid]['qty'] += $qty;
                $merged[$pid]['cost'] = round($total / $merged[$pid]['qty'], 2);
            } else {
                $merged[$pid] = ['qty' => $qty, 'cost' => $cost];
            }
        }
        if (! $merged) {
            throw new DomainException('Tambahkan minimal satu produk.');
        }
        foreach ($merged as $pid => $m) {
            $po->items()->create(['product_id' => $pid, 'qty_ordered' => $m['qty'], 'qty_received' => 0, 'unit_cost' => $m['cost']]);
            $subtotal += $m['qty'] * $m['cost'];
        }
        $po->update(['subtotal' => $subtotal, 'total' => $subtotal + (float) $po->shipping_cost]);
    }

    public function markOrdered(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if ($po->status !== 'draft') {
                throw new DomainException('Hanya pembelian berstatus Draft yang bisa ditandai dipesan.');
            }
            $po->update(['status' => 'ordered', 'ordered_at' => now()]);
        });
    }

    public function cancel(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po = PurchaseOrder::whereKey($po->id)->lockForUpdate()->firstOrFail();
            if (! in_array($po->status, ['draft', 'ordered'], true)) {
                throw new DomainException('Pembelian yang sudah diterima sebagian atau seluruhnya tidak bisa dibatalkan.');
            }
            $po->update(['status' => 'cancelled']);
        });
    }

    /** HPP baru: rata-rata tertimbang stok lama dan barang yang baru masuk. */
    public static function weightedCost(int $oldStock, ?float $oldCost, int $qty, float $landedUnitCost): float
    {
        if ($oldCost === null || $oldStock <= 0) {
            return round($landedUnitCost, 2);
        }

        return round(($oldStock * $oldCost + $qty * $landedUnitCost) / ($oldStock + $qty), 2);
    }

    /**
     * @param array<int,int|string> $received item_id => jumlah yang tiba sekarang
     * @return int total unit yang diterima pada penerimaan ini
     */
    public function receive(PurchaseOrder $po, array $received, ?int $userId): int
    {
        return DB::transaction(function () use ($po, $received, $userId) {
            $po = PurchaseOrder::with('supplier')->whereKey($po->id)->lockForUpdate()->firstOrFail();
            if (! in_array($po->status, ['ordered', 'partial'], true)) {
                throw new DomainException('Barang hanya bisa diterima untuk pembelian berstatus Dipesan atau Diterima sebagian.');
            }

            $items = $po->items()->lockForUpdate()->get();
            // Ongkir/bea dibagi menurut nilai barang: setiap unit mendapat tambahan persentase yang sama.
            $factor = $po->subtotal > 0 ? 1 + ((float) $po->shipping_cost / (float) $po->subtotal) : 1;
            $units = 0;

            foreach ($items as $item) {
                $qty = (int) ($received[$item->id] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $item->remaining) {
                    throw new DomainException("Jumlah diterima melebihi sisa pesanan ({$item->remaining} unit) untuk salah satu produk.");
                }

                $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                $newCost = self::weightedCost((int) $product->stock, $product->cost_price !== null ? (float) $product->cost_price : null, $qty, (float) $item->unit_cost * $factor);

                $product->update(['stock' => (int) $product->stock + $qty, 'cost_price' => $newCost]);
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $qty,
                    'reference_type' => 'purchase',
                    'reference_id' => $po->id,
                    'note' => "Pembelian {$po->po_number}" . ($po->supplier ? " dari {$po->supplier->name}" : ''),
                    'user_id' => $userId,
                ]);
                $item->update(['qty_received' => $item->qty_received + $qty]);
                $units += $qty;
            }

            if ($units === 0) {
                throw new DomainException('Isi jumlah barang yang diterima (minimal 1 unit).');
            }

            $complete = $po->items()->get()->every(fn ($i) => $i->qty_received >= $i->qty_ordered);
            $po->update(['status' => $complete ? 'received' : 'partial', 'received_at' => $complete ? now() : $po->received_at]);

            return $units;
        });
    }
}
