<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceTicket extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'baru' => 'Baru',
        'diterima' => 'Diterima',
        'diperiksa' => 'Diperiksa',
        'menunggu_persetujuan' => 'Menunggu persetujuan biaya',
        'dikerjakan' => 'Dikerjakan',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    /** Alur status yang diperbolehkan. */
    public const FLOW = [
        'baru' => ['diterima', 'ditolak', 'dibatalkan'],
        'diterima' => ['diperiksa', 'ditolak'],
        'diperiksa' => ['menunggu_persetujuan', 'dikerjakan', 'ditolak'],
        'menunggu_persetujuan' => ['dikerjakan', 'dibatalkan'],
        'dikerjakan' => ['selesai'],
        'selesai' => [], 'ditolak' => [], 'dibatalkan' => [],
    ];

    public const ISSUE_TYPES = [
        'tidak_menyala' => 'Mesin tidak menyala',
        'bocor' => 'Bocor / kebocoran air',
        'tekanan' => 'Tekanan atau ekstraksi bermasalah',
        'grinder' => 'Grinder macet / hasil gilingan tidak rata',
        'error' => 'Muncul kode error',
        'suara' => 'Suara tidak normal',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'ticket_number', 'user_id', 'warranty_id', 'order_id', 'product_name', 'serial_number', 'issue_type', 'description', 'photos',
        'contact_phone', 'status', 'in_warranty', 'admin_note', 'estimate_cost', 'final_cost', 'paid_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['photos' => 'array', 'in_warranty' => 'boolean', 'estimate_cost' => 'decimal:2', 'final_cost' => 'decimal:2', 'paid_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceTicketEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function issueLabel(): string
    {
        return self::ISSUE_TYPES[$this->issue_type] ?? $this->issue_type;
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['selesai', 'ditolak', 'dibatalkan'], true);
    }

    public static function generateNumber(): string
    {
        $prefix = 'SRV-'.now('Asia/Jakarta')->format('Ymd').'-';
        $last = static::withTrashed()->where('ticket_number', 'like', $prefix.'%')->orderByDesc('ticket_number')->value('ticket_number');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }
}
