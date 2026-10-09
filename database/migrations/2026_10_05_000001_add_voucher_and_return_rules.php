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
            $table->string('description')->nullable()->after('code');
            // 0 = tanpa batas. Default 1 = satu kali pakai per user (standar e-commerce).
            $table->unsignedInteger('per_user_limit')->default(1)->after('quota');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Snapshot kode: tetap terbaca di riwayat/invoice walau voucher dihapus.
            $table->string('voucher_code', 50)->nullable()->after('voucher_id');
            // Patokan jendela retur 7 hari (PRD): sejak kapan order berstatus selesai.
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        // Isi untuk order selesai yang sudah ada: waktu tiba di shipment, kalau tidak ada pakai updated_at.
        DB::table('orders')->where('status', 'selesai')->whereNull('completed_at')->orderBy('id')->each(function ($o) {
            $delivered = DB::table('shipments')->where('order_id', $o->id)->max('delivered_at');
            DB::table('orders')->where('id', $o->id)->update(['completed_at' => $delivered ?: $o->updated_at]);
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->string('reason_type', 30)->default('lainnya')->after('reason');
            $table->json('evidence')->nullable()->after('reason_type');           // path foto (disk privat)
            $table->string('refund_bank', 50)->nullable()->after('refund_amount');
            $table->string('refund_account_number', 40)->nullable()->after('refund_bank');
            $table->string('refund_account_name', 100)->nullable()->after('refund_account_number');
            $table->boolean('restocked')->default(false)->after('refund_account_name');
            $table->timestamp('refunded_at')->nullable()->after('approved_at');
            $table->unique('order_id');                                            // PRD: Order -> Return one-to-one
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropColumn(['reason_type', 'evidence', 'refund_bank', 'refund_account_number', 'refund_account_name', 'restocked', 'refunded_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['voucher_code', 'completed_at']);
        });
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['description', 'per_user_limit']);
        });
    }
};
