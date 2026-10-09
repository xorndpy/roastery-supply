<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SettingSeeder lama menyimpan data karangan (telepon, alamat, sosmed, logo yang filenya tidak ada).
 * Hapus HANYA baris yang nilainya masih persis sama dengan seeder lama, sehingga
 * apa pun yang sudah diisi admin tetap aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        $placeholders = [
            'site_logo'         => 'assets/images/logo.png',
            'site_favicon'      => 'assets/images/favicon.ico',
            'contact_phone'     => '+62 812-3456-7890',
            'contact_whatsapp'  => '+6281234567890',
            'contact_email'     => 'info@roasterysupply.com',
            'company_address'   => 'Jl. Senopati No. 88, Kebayoran Baru, Jakarta Selatan, DKI Jakarta 12190',
            'social_instagram'  => 'https://instagram.com/roasterysupply',
            'social_facebook'   => 'https://facebook.com/roasterysupply',
            'social_youtube'    => 'https://youtube.com/@roasterysupply',
        ];

        foreach ($placeholders as $key => $value) {
            DB::table('settings')->where('key', $key)->where('value', $value)->delete();
        }
    }

    public function down(): void
    {
        // Data karangan tidak dipulihkan.
    }
};
