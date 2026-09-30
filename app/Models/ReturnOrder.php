<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReturnOrder extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Target table name for returns.
     */
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'reason',
        'status',
        'refund_amount',
        'approved_by',
        'approved_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'refund_amount' => 'decimal:2',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Associated order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * User (admin) who approved or rejected the return.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope pending returns.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
