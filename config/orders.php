<?php

return [
    // Batas waktu proses (jam) sebelum pesanan ditandai "terlambat".
    // Dihitung dari perubahan status terakhir (updated_at).
    'sla_hours' => [
        'dikonfirmasi' => (int) env('ORDER_SLA_CONFIRMED_HOURS', 24), // belum dikemas
        'diproses' => (int) env('ORDER_SLA_PROCESSING_HOURS', 48),    // belum dikirim
    ],
    'per_page_options' => [10, 25, 50, 100],
];
