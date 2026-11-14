<?php

namespace App\Support;

/** Hitung perkiraan biaya payment gateway dari aturan di config/finance.php. */
class GatewayFee
{
    public static function for(?string $paymentType, float $amount): float
    {
        $rules = config('finance.gateway_fees', []);
        $rule = $rules[(string) $paymentType] ?? $rules['default'] ?? ['fixed' => 0, 'percent' => 0];

        return round(((float) ($rule['fixed'] ?? 0)) + $amount * ((float) ($rule['percent'] ?? 0)) / 100, 2);
    }
}
