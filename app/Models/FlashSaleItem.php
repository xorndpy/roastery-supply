<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleItem extends Model
{
    protected $fillable = ['flash_sale_id', 'product_id', 'sale_price', 'quota', 'max_per_order'];

    protected function casts(): array
    {
        return ['sale_price' => 'decimal:2'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function discountPercent(): int
    {
        $price = (float) ($this->product->price ?? 0);

        return $price > 0 ? max(0, (int) round((1 - (float) $this->sale_price / $price) * 100)) : 0;
    }
}
