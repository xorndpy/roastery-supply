<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'proof_image',
        'status',
        'verified_by',
        'verified_at',
        'note',
        // Gateway (Midtrans)
        'provider',
        'provider_order_id',
        'provider_transaction_id',
        'payment_type',
        'gateway_status',
        'snap_token',
        'redirect_url',
        'gateway_meta',
        'expires_at',
        'paid_at',
        'last_checked_at',
        'review_reason',
        'reviewed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'verified_at' => 'datetime',
        'gateway_meta' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** Alasan pengecualian -> [judul singkat, penjelasan + langkah untuk admin]. */
    public const REASONS = [
        'stok_kurang' => [
            'Dibayar, stok tidak cukup',
            'Uang sudah masuk tetapi stok tidak mencukupi sehingga pesanan belum bisa dikonfirmasi. Tambah stok lalu klik Setujui, atau refund lewat dashboard Midtrans lalu tandai sudah refund.',
        ],
        'pembayaran_ganda' => [
            'Pembayaran ganda',
            'Pesanan ini sudah lunas lewat pembayaran lain, sehingga pembayaran ini tidak diterapkan ke pesanan. Refund di dashboard Midtrans, lalu tandai sudah refund.',
        ],
        'dibayar_setelah_batal' => [
            'Dibayar setelah pesanan batal',
            'Pelanggan membayar setelah pesanan dibatalkan. Refund di dashboard Midtrans, lalu tandai sudah refund.',
        ],
        'nominal_tidak_cocok' => [
            'Nominal tidak sesuai tagihan',
            'Jumlah yang dibayar berbeda dari tagihan. Periksa dulu. Klik Setujui bila tetap diterima, atau refund lalu tandai sudah refund.',
        ],
        'fraud_challenge' => [
            'Ditahan sistem anti-fraud',
            'Midtrans menandai transaksi kartu ini perlu ditinjau. Putuskan (approve/deny) di dashboard Midtrans, lalu klik Sinkronkan. Status akan ikut terbarui otomatis.',
        ],
        'refund_chargeback' => [
            'Refund / chargeback / pembatalan di gateway',
            'Ada refund, chargeback, atau pembatalan pada pembayaran yang sudah dikonfirmasi. Tinjau pesanannya (batalkan bila perlu), lalu tandai selesai ditinjau.',
        ],
        'gagal_diproses' => [
            'Notifikasi gagal diproses',
            'Sistem gagal memproses notifikasi pembayaran setelah beberapa percobaan. Klik Sinkronkan. Jika berulang, hubungi pengembang.',
        ],
    ];

    public const STATUS_LABELS = [
        'waiting'   => 'Menunggu pelanggan',
        'pending'   => 'Menunggu verifikasi',
        'verified'  => 'Terkonfirmasi',
        'rejected'  => 'Ditolak',
        'expired'   => 'Kedaluwarsa',
        'review'    => 'Perlu ditinjau',
        'duplicate' => 'Pembayaran ganda',
        'refunded'  => 'Sudah direfund',
    ];

    public function isGateway(): bool
    {
        return $this->provider !== null;
    }

    public function methodLabel(): string
    {
        if ($this->isGateway()) {
            return match ($this->payment_type) {
                null           => 'Midtrans (belum memilih metode)',
                'bank_transfer' => 'Virtual Account',
                'echannel'     => 'Mandiri Bill',
                'gopay'        => 'GoPay',
                'qris'         => 'QRIS',
                'credit_card'  => 'Kartu kredit',
                'cstore'       => 'Minimarket',
                'shopeepay'    => 'ShopeePay',
                default        => ucfirst(str_replace('_', ' ', $this->payment_type)),
            };
        }

        return ['transfer_bca' => 'Transfer BCA', 'transfer_mandiri' => 'Transfer Mandiri', 'transfer_bni' => 'Transfer BNI'][$this->method] ?? (string) $this->method;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function reasonTitle(): ?string
    {
        return $this->review_reason ? (self::REASONS[$this->review_reason][0] ?? $this->review_reason) : null;
    }

    public function reasonHelp(): ?string
    {
        return $this->review_reason ? (self::REASONS[$this->review_reason][1] ?? null) : null;
    }

    /** Admin perlu bertindak: bukti manual menunggu dicek, atau pengecualian gateway yang belum ditinjau. */
    public function requiresAttention(): bool
    {
        return $this->status === 'pending' || ($this->review_reason !== null && $this->reviewed_at === null);
    }

    /** Pembayaran gateway yang masih bisa berubah (belum final). */
    public function isOpen(): bool
    {
        return in_array($this->status, ['waiting', 'review'], true);
    }

    /** Perlu perhatian: bukti manual (pending) ATAU pengecualian gateway yang belum ditinjau. */
    public function scopeNeedsAttention($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'pending')
              ->orWhere(fn ($w) => $w->whereNotNull('review_reason')->whereNull('reviewed_at'));
        });
    }

    public function scopeGateway($query)
    {
        return $query->whereNotNull('provider');
    }

    /**
     * Relasi ke Order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relasi ke User yang verify (admin/staff)
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope: pembayaran pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: pembayaran verified
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Scope: pembayaran rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Audit log (PRD 6.7): siapa mengubah apa. Hanya field penting yang dicatat.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pembayaran')
            ->setDescriptionForEvent(fn (string $event) => "Pembayaran {$event}");
    }
}
