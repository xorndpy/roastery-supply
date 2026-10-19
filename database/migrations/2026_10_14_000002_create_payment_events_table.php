<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap notifikasi dari gateway (webhook) dan hasil pengecekan terjadwal.
 *
 * Satu kejadian yang sama (misalnya notifikasi "settlement" yang dikirim dua kali) menghasilkan event_key yang
 * sama, sehingga indeks UNIQUE (provider, event_key) menolak baris kedua. Itulah yang membuat notifikasi ganda
 * tidak pernah diproses dua kali. Tabel ini juga menjadi bukti audit: apa yang diterima, kapan, dan hasilnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_key', 80);
            $table->string('provider_order_id', 64)->nullable()->index();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('transaction_status', 30)->nullable();
            $table->string('fraud_status', 20)->nullable();
            $table->string('source', 20)->default('webhook');          // webhook | reconcile | simulasi
            $table->boolean('signature_valid')->default(true);
            $table->json('payload')->nullable();
            $table->string('outcome', 40)->nullable();
            $table->text('outcome_note')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['provider', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
