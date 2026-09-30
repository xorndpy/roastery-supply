<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'price',
        'quantity',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'product_id' => 'integer',
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * Parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Associated product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
