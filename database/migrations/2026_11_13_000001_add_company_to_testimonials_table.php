<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            // Perusahaan / jabatan, tampil di bawah nama (mis. "Owner · PT Kopi Nusantara, Jakarta").
            $table->string('company', 120)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }
};
