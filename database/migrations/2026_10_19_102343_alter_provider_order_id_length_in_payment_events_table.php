<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            // Ubah menjadi string 100 karakter (atau lebih)
            $table->string('provider_order_id', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->string('provider_order_id', 50)->change(); // sesuaikan dengan ukuran sebelumnya
        });
    }
};