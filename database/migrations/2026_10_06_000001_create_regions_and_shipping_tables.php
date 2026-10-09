<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10)->unique();
            $table->string('name', 120);        // "Kabupaten Bandung", "Kota Bandung"
            $table->timestamps();
        });

        // Layanan pengiriman yang ditawarkan toko (mis. "Kargo Reguler"). Tarif diatur admin per wilayah.
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tarif per layanan x wilayah. city_id NULL = berlaku untuk seluruh provinsi; baris dengan city_id = pengecualian kota.
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('rate_per_kg', 15, 2);
            $table->decimal('min_charge', 15, 2)->default(0);
            $table->unsignedSmallInteger('etd_min')->nullable();   // estimasi hari
            $table->unsignedSmallInteger('etd_max')->nullable();
            $table->timestamps();
            $table->index(['shipping_method_id', 'province_id', 'city_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('province')->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('province_id')->constrained()->nullOnDelete();
        });

        // Snapshot tujuan & layanan di order: tidak berubah walau tarif/profil diedit kemudian.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_method', 100)->nullable()->after('shipping_cost');
            $table->string('shipping_province', 100)->nullable()->after('shipping_address');
            $table->string('shipping_city', 120)->nullable()->after('shipping_province');
            $table->string('shipping_postal_code', 10)->nullable()->after('shipping_city');
            $table->unsignedInteger('shipping_weight')->nullable()->after('shipping_postal_code'); // gram
            $table->string('shipping_etd', 30)->nullable()->after('shipping_weight');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_method', 'shipping_province', 'shipping_city', 'shipping_postal_code', 'shipping_weight', 'shipping_etd']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('province_id');
        });
        Schema::dropIfExists('shipping_rates');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
