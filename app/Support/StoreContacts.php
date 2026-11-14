<?php

namespace App\Support;

use App\Models\Setting;

/** Penerima notifikasi toko, hanya dari Pengaturan (tanpa cadangan ke email kontak publik). */
class StoreContacts
{
    /** @return array<int,string> */
    public static function emails(): array
    {
        $raw = (string) Setting::get('order_notify_email');

        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[,;\s]+/', $raw) ?: []),
            fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)
        )));
    }
}
