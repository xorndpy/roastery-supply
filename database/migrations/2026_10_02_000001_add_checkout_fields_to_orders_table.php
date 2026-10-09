<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Kunci anti-duplikat checkout (double-click / refresh / dua tab).
            $table->string('idempotency_key', 64)->nullable()->unique()->after('order_number');

            // Snapshot alamat kirim. Tanpa ini, alamat order lama ikut berubah
            // saat customer mengedit profil karena order hanya menyimpan customer_id.
            $table->string('shipping_name')->nullable()->after('note');
            $table->string('shipping_phone', 30)->nullable()->after('shipping_name');
            $table->text('shipping_address')->nullable()->after('shipping_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'shipping_name', 'shipping_phone', 'shipping_address']);
        });
    }
};
