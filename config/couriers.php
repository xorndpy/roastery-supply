<?php

/**
 * Daftar kurir untuk form input resi (dropdown). Tambah/ubah di sini bila perlu.
 * Kurir yang tidak ada di daftar tetap bisa diisi lewat pilihan "Lainnya".
 */
return [
    'list' => [
        'JNE', 'J&T', 'SiCepat', 'AnterAja', 'Ninja Xpress', 'ID Express',
        'POS Indonesia', 'TIKI', 'Lion Parcel', 'Wahana', 'SAP Express',
        'Sentral Cargo', 'Kurir Toko (antar sendiri)',
    ],

    // Situs cek resi untuk pelanggan (pelanggan menyalin resi lalu menempel di sana).
    // Ganti ke situs kurir/agregator pilihan Anda; tidak ada parameter yang ditebak.
    'tracker_url' => env('COURIER_TRACKER_URL', 'https://cekresi.com'),
];
