<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'description',
        'price',
        'weight',
        'stock',
        'stock_threshold',
        'model_3d',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'brand_id' => 'integer',
            'price' => 'decimal:2',
            'weight' => 'integer',
            'stock' => 'integer',
            'stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Category relationship.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Brand relationship.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Product gallery images.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order', 'asc');
    }

    /**
     * Primary display image.
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Stock history movements.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Order items containing this product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope featured products.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Format price to IDR currency.
     */
    public function getPriceFormattedAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->price, 0, ',', '.');
    }

    /**
     * Generate standard SKU: RS-[CATEGORY]-[NUMBER] (e.g. RS-ESP-001)
     */
    public static function generateSku(string $categoryCode = 'GEN'): string
    {
        $prefix = 'RS-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $categoryCode), 0, 3));
        $count = static::withTrashed()->where('sku', 'like', "{$prefix}-%")->count() + 1;
        $sku = sprintf('%s-%03d', $prefix, $count);

        while (static::withTrashed()->where('sku', $sku)->exists()) {
            $count++;
            $sku = sprintf('%s-%03d', $prefix, $count);
        }

        return $sku;
    }
}
