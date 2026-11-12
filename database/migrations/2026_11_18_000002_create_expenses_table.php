<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Biaya operasional (gaji, sewa, listrik, iklan, dll.) untuk laporan laba rugi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->string('category', 40)->index();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
