<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    public const LIMIT = 5;

    protected $fillable = [
        'customer_id', 'label', 'recipient_name', 'phone', 'address',
        'province_id', 'city_id', 'postal_code', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** Satu baris ringkas untuk tampilan: "Kota, Provinsi 12345". */
    public function regionLine(): string
    {
        return trim(collect([$this->city?->name, $this->province?->name])->filter()->implode(', ') . ($this->postal_code ? ' ' . $this->postal_code : ''));
    }
}
