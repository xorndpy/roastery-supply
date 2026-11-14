<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tutup buku: periode yang sudah ditutup dikunci (biaya tidak bisa diubah) dan angkanya disimpan sebagai snapshot. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_closings', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end')->index();
            $table->json('snapshot');
            $table->text('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->useCurrent();
            $table->text('reopen_reason')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_closings');
    }
};
