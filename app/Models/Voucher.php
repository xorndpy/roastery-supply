<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'quota',
        'per_user_limit',
        'used_count',
        'start_at',
        'end_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'quota' => 'integer',
            'per_user_limit' => 'integer',
            'used_count' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Orders using this voucher.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Scope active and valid vouchers.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Status tampilan untuk admin: nonaktif / terjadwal / berakhir / habis / aktif. */
    public function getStateAttribute(): string
    {
        if (!$this->is_active) return 'nonaktif';
        if ($this->start_at && $this->start_at->isFuture()) return 'terjadwal';
        if ($this->end_at && $this->end_at->isPast()) return 'berakhir';
        if ($this->quota > 0 && $this->used_count >= $this->quota) return 'habis';

        return 'aktif';
    }
}
