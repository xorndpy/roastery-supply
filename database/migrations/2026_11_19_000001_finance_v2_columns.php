<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('gateway_fee', 15, 2)->nullable()->after('amount'); // biaya gateway saat pembayaran terverifikasi
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('attachment')->nullable()->after('note');           // bukti/nota (disk privat)
            $table->boolean('is_recurring')->default(false)->after('attachment');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn(['attachment', 'is_recurring']));
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('gateway_fee'));
    }
};
