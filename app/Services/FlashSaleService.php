<?php

namespace App\Services;

use App\Exceptions\FlashSaleChanged;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Harga promo flash sale. Sumber kebenaran harga: database, dihitung ulang tiap keranjang disegarkan dan
 * lagi di dalam transaksi checkout (reserve) dengan baris kuota dikunci. Kuota terjual = jumlah order_items
 * yang menunjuk item promo pada pesanan yang tidak batal, jadi pesanan batal/kedaluwarsa otomatis mengembalikan kuota.
 */
class FlashSaleService
{
    /** Item promo dari kampanye yang sedang berjalan, per product_id, lengkap dengan ->sold dan ->remaining. Disimpan per request. */
    public function items(): Collection
    {
        $attrs = request()->attributes;
        if ($attrs->has('flash_items')) {
            return $attrs->get('flash_items');
        }

        $items = FlashSaleItem::query()->whereHas('sale', fn ($q) => $q->running())->with('sale')->get();
        $sold = $this->soldFor($items->pluck('id')->all());
        foreach ($items as $i) {
            $i->sold = (int) ($sold[$i->id] ?? 0);
            $i->remaining = max(0, $i->quota - $i->sold);
        }

        $map = $items->sortBy('sale_price')->keyBy('product_id');
        $attrs->set('flash_items', $map);

        return $map;
    }

    public function flush(): void
    {
        request()->attributes->remove('flash_items');
    }

    /** @return array<int,int> item_id => jumlah terjual */
    public function soldFor(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('order_items.deleted_at')->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', 'batal')
            ->whereIn('order_items.flash_sale_item_id', $itemIds)
            ->groupBy('order_items.flash_sale_item_id')
            ->selectRaw('order_items.flash_sale_item_id as fid, SUM(order_items.quantity) as qty')
            ->pluck('qty', 'fid')->map(fn ($v) => (int) $v)->all();
    }

    /** Item promo yang masih bisa dibeli (berjalan dan kuota tersisa) untuk produk ini. */
    public function itemFor(int $productId): ?FlashSaleItem
    {
        $i = $this->items()->get($productId);

        return $i && $i->remaining > 0 ? $i : null;
    }

    public function priceFor(Product $product): float
    {
        $i = $this->itemFor($product->id);

        return $i ? (float) $i->sale_price : (float) $product->price;
    }

    /** Jumlah maksimum per pesanan untuk harga promo (sisa kuota dan batas per pesanan). */
    public function maxQty(FlashSaleItem $item): int
    {
        return $item->max_per_order ? min($item->remaining, (int) $item->max_per_order) : $item->remaining;
    }

    /**
     * Isi etalase: semua item promo dari SEMUA kampanye yang sedang berjalan (sama dengan halaman "Lihat semua"),
     * yang masih tersedia lebih dulu, lalu yang paling cepat berakhir. null bila tidak ada promo berjalan.
     *
     * @return array{items:Collection,ends_at:\Illuminate\Support\Carbon,campaigns:int,name:?string}|null
     */
    public function showcase(int $limit = 12): ?array
    {
        $running = $this->items();
        if ($running->isEmpty()) {
            return null;
        }

        $items = FlashSaleItem::whereIn('id', $running->pluck('id'))->with(['sale', 'product.images', 'product.brand'])->get()
            ->filter(fn ($i) => $i->product && $i->product->is_active)
            ->each(function ($i) use ($running) {
                $r = $running->get($i->product_id);
                $i->sold = $r->sold ?? 0;
                $i->remaining = $r->remaining ?? 0;
            })
            ->sortBy([
                [fn ($i) => $i->remaining > 0 && $i->product->stock > 0 ? 0 : 1, 'asc'],
                [fn ($i) => $i->sale->ends_at->timestamp, 'asc'],
                ['id', 'asc'],
            ])->values()->take($limit);

        if ($items->isEmpty()) {
            return null;
        }

        $campaigns = $items->pluck('flash_sale_id')->unique();

        return [
            'items' => $items,
            'ends_at' => $items->min(fn ($i) => $i->sale->ends_at),
            'campaigns' => $campaigns->count(),
            'name' => $campaigns->count() === 1 ? $items->first()->sale->name : null,
        ];
    }

    /** Kampanye berjalan yang paling cepat berakhir. */
    public function activeCampaign(): ?FlashSale
    {
        return FlashSale::running()->whereHas('items')->orderBy('ends_at')->first();
    }

    /**
     * Dipanggil DI DALAM transaksi checkout: kunci baris item promo, lalu pastikan promo masih berjalan,
     * harga sama, dan kuota/batas per pesanan cukup. Gagal = FlashSaleChanged (transaksi di-rollback pemanggil).
     */
    public function reserve(array $cart): void
    {
        $ids = collect($cart)->pluck('flash_item_id')->filter()->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $locked = FlashSaleItem::whereIn('id', $ids)->lockForUpdate()->with('sale')->get()->keyBy('id');
        $sold = $this->soldFor($ids);

        foreach ($cart as $line) {
            if (empty($line['flash_item_id'])) {
                continue;
            }
            $name = $line['name'] ?? 'Produk';
            $item = $locked->get($line['flash_item_id']);

            if (! $item || ! $item->sale || ! $item->sale->isRunning()) {
                throw new FlashSaleChanged("Promo \"{$name}\" sudah berakhir. Harga kembali normal, periksa keranjangmu.");
            }
            if ((float) $line['price'] !== (float) $item->sale_price) {
                throw new FlashSaleChanged("Harga promo \"{$name}\" berubah. Periksa keranjangmu.");
            }
            $remaining = $item->quota - ($sold[$item->id] ?? 0);
            if ($line['quantity'] > $remaining) {
                throw new FlashSaleChanged("Kuota promo \"{$name}\" tinggal {$remaining}. Kurangi jumlahnya di keranjang.");
            }
            if ($item->max_per_order && $line['quantity'] > $item->max_per_order) {
                throw new FlashSaleChanged("Promo \"{$name}\" dibatasi {$item->max_per_order} per pesanan.");
            }
        }
    }
}
