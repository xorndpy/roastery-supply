<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Flash sale: kampanye berjadwal, harga promo + kuota per produk. Kuota terjual dihitung dari order_items (pesanan batal otomatis membebaskan kuota). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('sale_price', 15, 2);
            $table->unsignedInteger('quota');
            $table->unsignedSmallInteger('max_per_order')->nullable();
            $table->timestamps();
            $table->unique(['flash_sale_id', 'product_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('flash_sale_item_id')->nullable()->after('cost_price')->constrained('flash_sale_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flash_sale_item_id');
        });
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
    }
};
