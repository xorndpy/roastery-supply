<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = ['shipping_method_id', 'province_id', 'city_id', 'rate_per_kg', 'min_charge', 'etd_min', 'etd_max'];

    protected function casts(): array
    {
        return ['rate_per_kg' => 'decimal:2', 'min_charge' => 'decimal:2', 'etd_min' => 'integer', 'etd_max' => 'integer'];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** "2-4 hari", "3 hari", atau null. */
    public function getEtdLabelAttribute(): ?string
    {
        if (!$this->etd_min && !$this->etd_max) {
            return null;
        }
        $min = $this->etd_min ?: $this->etd_max;
        $max = $this->etd_max ?: $this->etd_min;

        return $min === $max ? "{$min} hari" : "{$min}-{$max} hari";
    }
}
