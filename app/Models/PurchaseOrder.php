<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'ordered' => 'Dipesan',
        'partial' => 'Diterima sebagian',
        'received' => 'Diterima',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'po_number', 'supplier_id', 'status', 'ordered_at', 'expected_at', 'received_at',
        'subtotal', 'shipping_cost', 'total', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
            'expected_at' => 'date',
            'subtotal' => 'float',
            'shipping_cost' => 'float',
            'total' => 'float',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Masih menunggu barang tiba. */
    public function scopeAwaiting($query)
    {
        return $query->whereIn('status', ['ordered', 'partial']);
    }

    public static function generateNumber(): string
    {
        $prefix = 'PO-' . now('Asia/Jakarta')->format('Ymd') . '-';
        $n = static::where('po_number', 'like', $prefix . '%')->count() + 1;
        while (static::where('po_number', $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
