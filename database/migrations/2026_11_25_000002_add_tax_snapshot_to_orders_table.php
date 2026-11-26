<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Simpan tarif dan nilai PPN saat pesanan dibuat, supaya invoice lama tidak berubah bila tarif diganti. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('discount');
            $table->decimal('tax_amount', 14, 2)->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
    }
};
