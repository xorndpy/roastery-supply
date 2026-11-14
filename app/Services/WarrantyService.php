<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Warranty;
use Carbon\Carbon;

/** Penerbitan garansi per unit saat pesanan selesai. Aman dipanggil berulang (tidak membuat dobel). */
class WarrantyService
{
    public function monthsFor(?Product $product): int
    {
        $m = $product?->warranty_months;
        if ($m === null) {
            $m = (int) (Setting::get('warranty_default_months') ?? 12);
        }

        return max(0, (int) $m);
    }

    /** @return int jumlah garansi yang baru dibuat */
    public function issueFor(Order $order): int
    {
        $order->loadMissing('items.product');
        $start = Carbon::parse($order->completed_at ?? now())->timezone('Asia/Jakarta')->startOfDay();
        $created = 0;

        foreach ($order->items as $item) {
            if (Warranty::where('order_item_id', $item->id)->exists()) {
                continue;
            }
            $months = $this->monthsFor($item->product);
            if ($months <= 0) {
                continue;
            }
            for ($i = 0, $units = min((int) $item->quantity, 50); $i < $units; $i++) {
                Warranty::create([
                    'order_id' => $order->id, 'order_item_id' => $item->id, 'product_id' => $item->product_id, 'user_id' => $order->user_id,
                    'product_name' => $item->product_name, 'starts_at' => $start->toDateString(),
                    'expires_at' => $start->copy()->addMonthsNoOverflow($months)->toDateString(), 'status' => 'active',
                ]);
                $created++;
            }
        }

        return $created;
    }
}
