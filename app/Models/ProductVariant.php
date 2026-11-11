<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'product_image_id', 'type', 'name', 'color_hex', 'price_adjust', 'sort_order'];

    protected function casts(): array
    {
        return ['price_adjust' => 'decimal:2', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
