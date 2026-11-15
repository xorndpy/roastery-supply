<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    protected $fillable = ['name', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    public function scopeRunning(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function isRunning(): bool
    {
        return $this->is_active && $this->starts_at->lte(now()) && $this->ends_at->gt(now());
    }

    /** running | scheduled | ended | off */
    public function state(): string
    {
        if (! $this->is_active) {
            return 'off';
        }

        return $this->ends_at->lte(now()) ? 'ended' : ($this->starts_at->gt(now()) ? 'scheduled' : 'running');
    }
}
