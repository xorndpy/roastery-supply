<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'proof_image',
        'status',
        'verified_by',
        'verified_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'decimal:2',
            'verified_by' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Order associated with this payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * User (staff/admin) who verified the payment.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope pending payments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Verify payment and update order status.
     */
    public function verify(?int $userId = null): bool
    {
        $updated = $this->update([
            'status' => 'verified',
            'verified_by' => $userId ?? auth()->id(),
            'verified_at' => now(),
        ]);

        if ($updated && $this->order) {
            $this->order->confirmPayment();
        }

        return $updated;
    }

    /**
     * Reject payment with reason.
     */
    public function reject(?string $note = null, ?int $userId = null): bool
    {
        return $this->update([
            'status' => 'rejected',
            'verified_by' => $userId ?? auth()->id(),
            'verified_at' => now(),
            'note' => $note ?? $this->note,
        ]);
    }
}
