<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom untuk pembayaran lewat payment gateway (Midtrans).
 *
 * Status payments sekarang: waiting (menunggu pelanggan membayar di gateway), pending (bukti transfer manual
 * menunggu verifikasi), verified, rejected, expired, review (uang masuk tetapi butuh keputusan admin),
 * duplicate (pembayaran ganda), refunded.
 *
 * Pengaman terhadap pembayaran ganda ada di DATABASE, bukan hanya di kode:
 * provider_order_id dan provider_transaction_id memiliki indeks UNIQUE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->after('method')->index();
            $table->string('provider_order_id', 64)->nullable()->unique()->after('provider');
            $table->string('provider_transaction_id', 64)->nullable()->unique()->after('provider_order_id');
            $table->string('payment_type', 40)->nullable()->after('provider_transaction_id');
            $table->string('gateway_status', 30)->nullable()->after('payment_type');
            $table->string('snap_token', 100)->nullable()->after('gateway_status');
            $table->string('redirect_url', 500)->nullable()->after('snap_token');
            $table->json('gateway_meta')->nullable()->after('redirect_url');
            $table->timestamp('expires_at')->nullable()->after('gateway_meta')->index();
            $table->timestamp('paid_at')->nullable()->after('expires_at');
            $table->timestamp('last_checked_at')->nullable()->after('paid_at');
            $table->string('review_reason', 40)->nullable()->after('last_checked_at')->index();
            $table->timestamp('reviewed_at')->nullable()->after('review_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_order_id']);
            $table->dropUnique(['provider_transaction_id']);
            $table->dropIndex(['provider']);
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['review_reason']);
            $table->dropColumn([
                'provider', 'provider_order_id', 'provider_transaction_id', 'payment_type', 'gateway_status',
                'snap_token', 'redirect_url', 'gateway_meta', 'expires_at', 'paid_at', 'last_checked_at',
                'review_reason', 'reviewed_at',
            ]);
        });
    }
};
