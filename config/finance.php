<?php

return [
    /*
     * Perkiraan biaya payment gateway per metode (Midtrans), dihitung saat pembayaran terverifikasi dan disimpan
     * di payments.gateway_fee. Angka ini tarif umum yang SEBAIKNYA DISESUAIKAN dengan kontrak merchant-mu.
     * fixed = Rp per transaksi, percent = % dari nilai transaksi. Kunci = payments.payment_type dari Midtrans.
     */
    'gateway_fees' => [
        'bank_transfer' => ['fixed' => 4000, 'percent' => 0],     // Virtual Account BCA/BNI/BRI/Permata, dll.
        'echannel'      => ['fixed' => 4000, 'percent' => 0],     // Mandiri Bill
        'cstore'        => ['fixed' => 5000, 'percent' => 0],     // Alfamart / Indomaret
        'qris'          => ['fixed' => 0,    'percent' => 0.7],
        'gopay'         => ['fixed' => 0,    'percent' => 2.0],
        'shopeepay'     => ['fixed' => 0,    'percent' => 2.0],
        'credit_card'   => ['fixed' => 2000, 'percent' => 2.9],
        'akulaku'       => ['fixed' => 0,    'percent' => 1.7],
        'default'       => ['fixed' => 0,    'percent' => 0],     // metode tak dikenal: tidak dihitung
    ],

    // Tarif PPN bawaan bila fitur PPN diaktifkan di Pengaturan > Keuangan.
    'default_tax_rate' => 11,
];
