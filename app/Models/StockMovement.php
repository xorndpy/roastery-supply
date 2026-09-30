<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'note',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'quantity' => 'integer',
            'reference_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * Product associated with movement.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * User who initiated movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
