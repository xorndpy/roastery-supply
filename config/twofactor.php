<?php

return [
    // true = semua akun panel admin (super_admin/admin/staff/gudang) WAJIB mengaktifkan 2FA.
    // false = 2FA opsional (hanya ditanyakan bagi akun yang sudah mengaktifkannya).
    'enforced' => env('TWO_FACTOR_ENFORCED', true),

    // Nama yang tampil di aplikasi authenticator.
    'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'Roastery Supply')),
];
