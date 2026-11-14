<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('tracking_status', 120)->nullable()->after('status');   // status terakhir dari kurir
            $table->json('tracking_events')->nullable()->after('tracking_status'); // riwayat perjalanan paket
            $table->timestamp('tracking_checked_at')->nullable()->after('tracking_events');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', fn (Blueprint $t) => $t->dropColumn(['tracking_status', 'tracking_events', 'tracking_checked_at']));
    }
};
