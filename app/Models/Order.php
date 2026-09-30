<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'user_id',
        'voucher_id',
        'status',
        'subtotal',
        'discount',
        'shipping_cost',
        'total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'user_id' => 'integer',
            'voucher_id' => 'integer',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Customer who placed the order.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * User account who placed or handled the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Voucher applied to the order.
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Order items list.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Payment attempts for the order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Latest payment for the order.
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * Shipments for the order.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Latest shipment for the order.
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    /**
     * Returns requested for the order.
     */
    public function returns(): HasMany
    {
        return $this->hasMany(ReturnOrder::class);
    }

    /**
     * Latest return record for the order.
     */
    public function returnOrder(): HasOne
    {
        return $this->hasOne(ReturnOrder::class)->latestOfMany();
    }

    /**
     * Alias for returnOrder.
     */
    public function return(): HasOne
    {
        return $this->returnOrder();
    }

    /**
     * Scope order status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Translate status to Indonesian label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'menunggu_bayar' => 'Menunggu Pembayaran',
            'dikonfirmasi' => 'Pembayaran Dikonfirmasi',
            'diproses' => 'Sedang Diproses',
            'dikirim' => 'Dalam Pengiriman',
            'selesai' => 'Selesai',
            'batal' => 'Dibatalkan',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * Generate unique order number: ORD-[YYYYMMDD]-[NUMBER]
     */
    public static function generateOrderNumber(): string
    {
        $datePrefix = 'ORD-' . date('Ymd');
        $count = static::withTrashed()->where('order_number', 'like', "{$datePrefix}-%")->count() + 1;
        $orderNumber = sprintf('%s-%04d', $datePrefix, $count);

        while (static::withTrashed()->where('order_number', $orderNumber)->exists()) {
            $count++;
            $orderNumber = sprintf('%s-%04d', $datePrefix, $count);
        }

        return $orderNumber;
    }

    /**
     * Confirm payment and deduct product stock (PRD Section 5 & 6.2).
     */
    public function confirmPayment(): bool
    {
        if ($this->status !== 'menunggu_bayar') {
            return false;
        }

        $this->update(['status' => 'dikonfirmasi']);

        foreach ($this->items as $item) {
            if ($item->product) {
                $item->product->decrement('stock', $item->quantity);
                $item->product->stockMovements()->create([
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'order',
                    'reference_id' => $this->id,
                    'note' => 'Pengurangan stok order #' . $this->order_number,
                    'user_id' => auth()->id() ?? $this->user_id,
                ]);
            }
        }

        return true;
    }

    /**
     * Process order (status -> diproses).
     */
    public function process(): bool
    {
        if ($this->status !== 'dikonfirmasi') {
            return false;
        }

        return $this->update(['status' => 'diproses']);
    }

    /**
     * Ship order (status -> dikirim).
     */
    public function ship(?string $trackingNumber = null, ?string $courier = null): bool
    {
        if (!in_array($this->status, ['dikonfirmasi', 'diproses'])) {
            return false;
        }

        $updated = $this->update(['status' => 'dikirim']);

        if ($trackingNumber) {
            $this->shipments()->create([
                'courier' => $courier,
                'tracking_number' => $trackingNumber,
                'status' => 'shipped',
                'shipped_at' => now(),
            ]);
        }

        return $updated;
    }

    /**
     * Complete order (status -> selesai).
     */
    public function complete(): bool
    {
        if ($this->status !== 'dikirim') {
            return false;
        }

        return $this->update(['status' => 'selesai']);
    }

    /**
     * Cancel order and restore stock if it was previously confirmed (PRD Section 11 Edge Cases).
     */
    public function cancel(?string $note = null): bool
    {
        if (in_array($this->status, ['selesai', 'batal'])) {
            return false;
        }

        $previousStatus = $this->status;
        $this->update([
            'status' => 'batal',
            'note' => $note ? ($this->note . "\nBatal: " . $note) : $this->note,
        ]);

        if (in_array($previousStatus, ['dikonfirmasi', 'diproses', 'dikirim'])) {
            foreach ($this->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                    $item->product->stockMovements()->create([
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'reference_type' => 'order',
                        'reference_id' => $this->id,
                        'note' => 'Pengembalian stok pembatalan order #' . $this->order_number,
                        'user_id' => auth()->id() ?? $this->user_id,
                    ]);
                }
            }
        }

        return true;
    }
}
