<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimonial extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_name',
        'customer_photo',
        'content',
        'rating',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
        ];
    }

    /**
     * Scope approved testimonials.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }
}
