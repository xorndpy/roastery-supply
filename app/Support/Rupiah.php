<?php

namespace App\Support;

/** Baca angka rupiah yang diketik dengan format Indonesia ("285.000", "1.250.000,50") menjadi angka biasa. */
class Rupiah
{
    public static function clean(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $t = trim($value);
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $t)) {
            return str_replace(',', '.', str_replace('.', '', $t));
        }

        return $value;
    }
}
