<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Order extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'order_number',
        'tax_rate',
        'tax_amount',
        'idempotency_key',
        'customer_id',
        'user_id',
        'voucher_id',
        'voucher_code',
        'completed_at',
        'status',
        'subtotal',
        'discount',
        'shipping_cost',
        'total',
        'note',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_method',
        'shipping_province',
        'shipping_city',
        'shipping_postal_code',
        'shipping_weight',
        'shipping_etd',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'user_id' => 'integer',
            'voucher_id' => 'integer',
            'completed_at' => 'datetime',
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
    /**
     * Pesanan yang melewati batas waktu proses (lihat config/orders.php).
     */
    /** Pesanan yang menunggu tindakan: sudah dibayar belum selesai dikemas/kirim, atau bukti bayar menunggu verifikasi. */
    public function scopeNeedsAction(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereIn('status', ['dikonfirmasi', 'diproses'])
              ->orWhere(fn ($w) => $w->where('status', 'menunggu_bayar')->whereHas('payments', fn ($p) => $p->where('status', 'pending')));
        });
    }

    public function scopeLate(Builder $query): Builder
    {
        $sla = config('orders.sla_hours');

        return $query->where(function (Builder $q) use ($sla) {
            $q->where(fn ($w) => $w->where('status', 'dikonfirmasi')->where('updated_at', '<=', now()->subHours($sla['dikonfirmasi'])))
              ->orWhere(fn ($w) => $w->where('status', 'diproses')->where('updated_at', '<=', now()->subHours($sla['diproses'])));
        });
    }

    public function getLateHoursAttribute(): ?int
    {
        $limit = config('orders.sla_hours')[$this->status] ?? null;
        if ($limit === null) {
            return null;
        }
        $hours = (int) $this->updated_at->diffInHours(now());

        return $hours >= $limit ? $hours : null;
    }

    public function getIsLateAttribute(): bool
    {
        return $this->late_hours !== null;
    }

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
     * Alamat kirim lengkap untuk ditampilkan. Order baru menyimpan jalan, kota, provinsi, kode pos
     * terpisah; order lama (sebelum alamat terstruktur) sudah berisi alamat lengkap di shipping_address.
     */
    public function getFullShippingAddressAttribute(): ?string
    {
        if (!$this->shipping_city) {
            return $this->shipping_address;
        }

        return trim(implode(', ', array_filter([$this->shipping_address, $this->shipping_city, trim($this->shipping_province . ' ' . $this->shipping_postal_code)])));
    }

    /** Batas akhir pengajuan retur (PRD: 7 hari sejak selesai). */
    public function getReturnDeadlineAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->completed_at?->copy()->addDays(\App\Services\ReturnService::WINDOW_DAYS);
    }

    public function testimonial(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Testimonial::class);
    }

    /**
     * Generate nomor order: ORD-[YYYYMMDD]-[NUMBER] (PRD bagian 10).
     * Memakai nomor tertinggi hari ini (bukan count) supaya tidak bentrok
     * walau ada order yang dihapus. Kolom order_number tetap unique sebagai pengaman.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD-' . date('Ymd') . '-';

        $last = static::withTrashed()
            ->where('order_number', 'like', $prefix . '%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor invoice: INV/[YYYYMM]/[NUMBER], diturunkan dari nomor order
     * (ORD-20260115-0042 -> INV/202601/0042).
     */
    public function getInvoiceNumberAttribute(): string
    {
        $parts = explode('-', $this->order_number);
        $ym = substr($parts[1] ?? $this->created_at->format('Ymd'), 0, 6);

        return 'INV/' . $ym . '/' . end($parts);
    }

    /** Batas waktu pembayaran (PRD aturan #6). */
    public function getPaymentDeadlineAttribute(): \Illuminate\Support\Carbon
    {
        return $this->created_at->copy()->addHours(\App\Services\OrderService::PAYMENT_WINDOW_HOURS);
    }

    /**
     * Perubahan status dan stok SENGAJA tidak ada di model.
     * Gunakan App\Services\OrderService.
     */

    /**
     * Audit log (PRD 6.7): siapa mengubah apa. Hanya field penting yang dicatat.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pesanan')
            ->setDescriptionForEvent(fn (string $event) => "Pesanan {$event}");
    }
}
