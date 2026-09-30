<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'image',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Parent product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
