<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // all = semua pelanggan; new_member = hanya untuk pesanan pertama.
            $table->string('audience', 20)->default('all')->after('type')->index();
            // false = kode rahasia (mis. untuk influencer): tetap bisa dipakai, tapi tidak dipajang ke pelanggan.
            $table->boolean('is_public')->default(true)->after('is_active');
        });

        // Voucher yang sudah ada TIDAK otomatis dipajang: bisa saja ada kode internal/rahasia di antaranya.
        // Admin memilih sendiri mana yang tampil lewat centang "Tampilkan ke pelanggan". Voucher baru default-nya tampil.
        DB::table('vouchers')->update(['is_public' => false]);
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['audience']);
            $table->dropColumn(['audience', 'is_public']);
        });
    }
};
