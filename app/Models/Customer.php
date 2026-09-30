<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'postal_code',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    /**
     * User account relation (1-to-1).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Customer orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
