<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Garansi per unit barang yang dibeli (dibuat otomatis saat pesanan selesai). */
class Warranty extends Model
{
    protected $fillable = ['order_id', 'order_item_id', 'product_id', 'user_id', 'product_name', 'serial_number', 'starts_at', 'expires_at', 'status', 'void_reason'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'expires_at' => 'date'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->toDateString() >= now('Asia/Jakarta')->toDateString();
    }

    public function label(): string
    {
        return $this->status === 'void' ? 'Dibatalkan' : ($this->isActive() ? 'Aktif' : 'Kedaluwarsa');
    }

    public function daysLeft(): int
    {
        return max(0, (int) now('Asia/Jakarta')->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false));
    }
}
