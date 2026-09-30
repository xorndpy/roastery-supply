<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'courier',
        'service',
        'tracking_number',
        'cost',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'cost' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * Scope pending shipments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
