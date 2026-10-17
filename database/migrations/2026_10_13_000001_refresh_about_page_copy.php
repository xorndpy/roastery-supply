<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Halaman Tentang kini memakai teks bawaan baru (config/cms.php). SettingSeeder lama menyimpan
 * satu kalimat bawaan ke database, sehingga teks baru tidak akan pernah tampil.
 * Hapus HANYA baris yang nilainya masih persis sama dengan teks bawaan lama: bila admin sudah
 * menulis profil sendiri, isinya tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'about_body')
            ->where('value', 'Kami menyediakan mesin kopi profesional untuk kedai kopi di Indonesia.')
            ->delete();
    }

    public function down(): void
    {
        // Teks lama tidak dipulihkan.
    }
};
