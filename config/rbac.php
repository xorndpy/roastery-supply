<?php

/**
 * Pengelompokan permission untuk matriks Role & Hak Akses (PRD 6.7).
 * Permission yang belum terdaftar di sini otomatis masuk grup "Lainnya".
 */
return [
    // Role yang tidak boleh diubah dari UI.
    'locked_roles' => ['super_admin', 'customer', 'guest'],

    'groups' => [
        'Katalog' => [
            'view_products', 'create_products', 'edit_products', 'delete_products', 'import_export_products',
            'view_categories', 'create_categories', 'edit_categories', 'delete_categories',
            'view_brands', 'create_brands', 'edit_brands', 'delete_brands',
            'view_stock', 'manage_stock',
        ],
        'Penjualan' => [
            'view_orders', 'edit_orders', 'cancel_orders', 'download_invoices',
            'verify_payments', 'reject_payments', 'manage_shipments', 'view_shipping_rates', 'manage_shipping_rates',
            'view_returns', 'approve_returns', 'reject_returns',
        ],
        'Pelanggan' => ['view_customers', 'edit_customers', 'export_customers'],
        'Pemasaran' => [
            'view_vouchers', 'create_vouchers', 'edit_vouchers', 'delete_vouchers',
            'view_banners', 'create_banners', 'edit_banners', 'delete_banners',
            'view_testimonials', 'approve_testimonials', 'delete_testimonials',
        ],
        'Konten (CMS)' => [
            'view_faqs', 'create_faqs', 'edit_faqs', 'delete_faqs',
            'view_partners', 'create_partners', 'edit_partners', 'delete_partners',
            'view_branches', 'create_branches', 'edit_branches', 'delete_branches',
            'view_settings', 'edit_settings',
            'view_contact_messages', 'reply_contact_messages', 'delete_contact_messages',
        ],
        'Laporan' => ['view_reports', 'export_reports'],
        'Administrasi' => [
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_roles', 'manage_roles', 'view_activity_logs',
        ],
        'Portal Customer' => [
            'view_own_orders', 'create_orders', 'request_returns', 'view_own_profile', 'edit_own_profile',
        ],
    ],

    // Label ramah untuk nama permission.
    'verbs' => [
        'view' => 'Lihat', 'create' => 'Tambah', 'edit' => 'Ubah', 'delete' => 'Hapus',
        'manage' => 'Kelola', 'verify' => 'Verifikasi', 'reject' => 'Tolak', 'approve' => 'Setujui',
        'cancel' => 'Batalkan', 'download' => 'Unduh', 'export' => 'Ekspor', 'reply' => 'Balas',
        'request' => 'Ajukan', 'import_export' => 'Impor/Ekspor',
    ],
];
