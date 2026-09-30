<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'is_read',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    /**
     * Scope unread messages.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Alias for unread messages.
     */
    public function scopePending(Builder $query): Builder
    {
        return $this->scopeUnread($query);
    }
}
