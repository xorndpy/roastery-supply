<?php

/**
 * Definisi pengaturan situs (PRD 6.5: tidak ada konten yang di-hardcode).
 * Nilai disimpan di tabel `settings` (key/value). Bagian 'default' dipakai bila belum diisi.
 * type: text | textarea | url | email | image
 */
return [
    'settings' => [
        'Umum' => [
            'site_name' => ['label' => 'Nama Usaha', 'type' => 'text', 'default' => 'Roastery Supply', 'max' => 100],
            'tagline'   => ['label' => 'Tagline', 'type' => 'text', 'default' => 'Mesin kopi profesional untuk kedai kopi serius.', 'max' => 160],
            'site_logo' => ['label' => 'Logo', 'type' => 'image', 'help' => 'PNG/JPG/WEBP, maks 2 MB.'],
        ],
        'Kontak' => [
            'contact_email'   => ['label' => 'Email', 'type' => 'email'],
            'contact_phone'   => ['label' => 'Telepon', 'type' => 'text', 'max' => 30],
            'contact_whatsapp' => ['label' => 'WhatsApp', 'type' => 'text', 'max' => 20, 'help' => 'Format 628xxxxxxxxxx (tanpa + dan spasi).'],
            'contact_address' => ['label' => 'Alamat', 'type' => 'textarea', 'max' => 500],
            'contact_hours' => ['label' => 'Jam Operasional', 'type' => 'text', 'default' => 'Senin – Sabtu, 08.00 – 17.00 WIB', 'max' => 100],
        ],
        'Media Sosial' => [
            'social_instagram' => ['label' => 'Instagram', 'type' => 'url'],
            'social_facebook'  => ['label' => 'Facebook', 'type' => 'url'],
            'social_tiktok'    => ['label' => 'TikTok', 'type' => 'url'],
            'social_linkedin' => ['label' => 'LinkedIn', 'type' => 'url'],
            'social_youtube'   => ['label' => 'YouTube', 'type' => 'url'],
        ],
        'Rekening Pembayaran' => [
            'bank_name'           => ['label' => 'Nama Bank', 'type' => 'text', 'max' => 50],
            'bank_account_number' => ['label' => 'Nomor Rekening', 'type' => 'text', 'max' => 40],
            'bank_account_name'   => ['label' => 'Atas Nama', 'type' => 'text', 'max' => 100],
            'payment_note'        => ['label' => 'Catatan Pembayaran', 'type' => 'textarea', 'max' => 500, 'help' => 'Tampil di halaman pesanan customer.'],
        ],
        'Pengiriman' => [
            'free_shipping_min' => ['label' => 'Gratis ongkir untuk belanja minimal (Rp)', 'type' => 'number', 'max' => 1000000000,
                'help' => 'Kosongkan atau 0 untuk menonaktifkan. Dihitung dari belanja setelah diskon voucher.'],
        ],
        'Halaman Tentang' => [
            'about_title'    => ['label' => 'Label Halaman', 'type' => 'text', 'default' => 'Tentang Kami', 'max' => 120, 'help' => 'Teks kecil di atas judul utama, juga dipakai sebagai judul tab browser.'],
            'about_headline' => ['label' => 'Judul Utama', 'type' => 'text', 'default' => 'Mitra peralatan kopi untuk usaha yang mengutamakan kualitas.', 'max' => 140],
            'about_lead'     => ['label' => 'Paragraf Pembuka', 'type' => 'textarea', 'rows' => 3, 'max' => 400,
                'default' => 'Kami menyediakan espresso machine, grinder, dan perlengkapan barista dengan garansi resmi, didukung layanan konsultasi, instalasi, dan purna jual bagi kedai kopi serta roastery di Indonesia.'],
            'about_body'     => ['label' => 'Profil Usaha', 'type' => 'textarea', 'max' => 5000, 'rows' => 10,
                'default' => "Peralatan adalah fondasi operasional sebuah kedai kopi. Karena itu, kami memandang penjualan mesin bukan sebagai transaksi sesaat, melainkan awal dari hubungan jangka panjang dengan pelaku usaha yang mengandalkan peralatannya setiap hari.\n\nSetiap rekomendasi kami susun berdasarkan kebutuhan nyata Anda: kapasitas produksi, konsep usaha, ketersediaan ruang, dan anggaran. Seluruh produk dipasok dari distributor resmi dan dilengkapi garansi, sehingga keaslian dan perlindungannya terjamin.\n\nKami menyadari bahwa kedai yang berhenti beroperasi berarti kehilangan pendapatan. Dukungan teknisi, ketersediaan suku cadang, serta pendampingan instalasi dan pelatihan barista kami siapkan agar peralatan Anda dapat bekerja optimal dalam jangka panjang.",
                'help' => 'Pisahkan paragraf dengan baris kosong. Tambahkan tahun berdiri, jumlah cabang, atau pencapaian hanya bila datanya benar.'],
            'about_photo'    => ['label' => 'Foto Profil Usaha', 'type' => 'image', 'help' => 'Disarankan landscape 1200 x 900 px (JPG/WEBP, ideal di bawah 300 KB). Mis. showroom, gudang, atau tim. Kosongkan bila belum ada: bagian ini tetap tampil rapi tanpa foto.'],
        ],
        'Tampilan' => [
            'hero_bg'         => ['label' => 'Background Beranda (Hero)', 'type' => 'image', 'help' => 'Disarankan 1920 x 1000 px (JPG/WEBP, ideal di bawah 400 KB, maks 2 MB). Tampil di belakang judul dan model 3D di beranda. Kosongkan untuk memakai gradasi coklat.'],
            'header_bg'       => ['label' => 'Background Header Halaman', 'type' => 'image', 'help' => 'Disarankan 1920 x 500 px (JPG/WEBP, ideal di bawah 300 KB, maks 2 MB). Dipakai di Katalog, Kontak, FAQ, dan halaman lain. Kosongkan untuk memakai latar polos.'],
            'about_header_bg' => ['label' => 'Foto Utama Halaman Tentang', 'type' => 'image', 'help' => 'Tampil di sisi kanan bagian atas halaman Tentang. Disarankan 1600 x 1000 px (JPG/WEBP, ideal di bawah 350 KB). Kosongkan untuk memakai pola biji kopi.'],
        ],
        'SEO' => [
            'meta_title'       => ['label' => 'Meta Title', 'type' => 'text', 'max' => 70],
            'meta_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'max' => 170, 'rows' => 2],
        ],
    ],
];
