<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/** Biaya operasional yang dicatat tim keuangan (masuk ke laporan laba rugi). */
class Expense extends Model
{
    use LogsActivity, SoftDeletes;

    public const CATEGORIES = [
        'gaji' => 'Gaji & honor',
        'sewa' => 'Sewa tempat',
        'utilitas' => 'Listrik, air & internet',
        'pemasaran' => 'Pemasaran & iklan',
        'logistik' => 'Packing & logistik',
        'operasional' => 'Operasional lainnya',
        'pajak' => 'Pajak & perizinan',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = ['expense_date', 'category', 'description', 'amount', 'note', 'attachment', 'is_recurring', 'created_by'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'is_recurring' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['expense_date', 'category', 'description', 'amount', 'note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('keuangan')
            ->setDescriptionForEvent(fn (string $event) => "Biaya {$event}");
    }
}
