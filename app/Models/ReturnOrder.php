<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ReturnOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    /**
     * Target table name for returns.
     */
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'reason',
        'reason_type',
        'evidence',
        'status',
        'refund_amount',
        'refund_bank',
        'refund_account_number',
        'refund_account_name',
        'restocked',
        'approved_by',
        'approved_at',
        'refunded_at',
        'note',
    ];

    public const REASON_TYPES = [
        'rusak'        => 'Barang rusak saat diterima',
        'tidak_sesuai' => 'Barang tidak sesuai pesanan',
        'cacat'        => 'Cacat / tidak berfungsi',
        'lainnya'      => 'Alasan lain',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'refund_amount' => 'decimal:2',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'refunded_at' => 'datetime',
            'evidence' => 'array',
            'restocked' => 'boolean',
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

    public function getReasonLabelAttribute(): string
    {
        return self::REASON_TYPES[$this->reason_type] ?? $this->reason_type;
    }

    /** pending -> approved (menunggu refund) -> refunded, atau rejected. */
    public function getStageAttribute(): string
    {
        return match (true) {
            $this->status === 'approved' && $this->refunded_at !== null => 'refunded',
            default => $this->status,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'refund_amount', 'restocked', 'refunded_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('retur')
            ->setDescriptionForEvent(fn (string $event) => "Retur {$event}");
    }
}
