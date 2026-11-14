<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Satu penutupan buku. Yang aktif (tidak di-soft-delete) mengunci periodenya. */
class FinanceClosing extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = ['period_start', 'period_end', 'snapshot', 'note', 'closed_by', 'closed_at', 'reopen_reason', 'reopened_by'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'snapshot' => 'array', 'closed_at' => 'datetime'];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['period_start', 'period_end', 'note', 'reopen_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('keuangan')
            ->setDescriptionForEvent(fn (string $event) => "Tutup buku {$event}");
    }
}
