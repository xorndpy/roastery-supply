<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['service_ticket_id', 'status', 'note', 'actor_id', 'is_public'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
