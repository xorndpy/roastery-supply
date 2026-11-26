<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->text('address');
            $table->foreignId('province_id')->nullable()->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('postal_code', 5)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });

        // Pelanggan lama: alamat yang sudah ada di profil dijadikan alamat utama di buku alamat.
        DB::table('customers')->whereNotNull('address')->where('address', '!=', '')->orderBy('id')->each(function ($c) {
            DB::table('customer_addresses')->insert([
                'customer_id' => $c->id,
                'label' => 'Alamat utama',
                'recipient_name' => $c->name,
                'phone' => $c->phone ?: '-',
                'address' => $c->address,
                'province_id' => $c->province_id,
                'city_id' => $c->city_id,
                'postal_code' => $c->postal_code,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
