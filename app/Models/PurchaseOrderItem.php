<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'qty_ordered', 'qty_received', 'unit_cost'];

    protected function casts(): array
    {
        return ['qty_ordered' => 'integer', 'qty_received' => 'integer', 'unit_cost' => 'float'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->qty_ordered - $this->qty_received);
    }
}
