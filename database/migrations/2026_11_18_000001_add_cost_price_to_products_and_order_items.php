<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HPP (harga pokok penjualan). products.cost_price = HPP terkini per unit;
 * order_items.cost_price = salinan HPP saat pesanan dibuat, supaya laba rugi lama tidak berubah bila HPP diperbarui.
 * Nullable: NULL berarti HPP belum diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->nullable()->after('price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('cost_price'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('cost_price'));
    }
};
