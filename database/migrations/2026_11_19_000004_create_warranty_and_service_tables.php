<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Garansi per unit barang yang dibeli + tiket servis (pengajuan, pemeriksaan, estimasi biaya, penyelesaian). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('warranty_months')->nullable()->after('cost_price'); // NULL = pakai bawaan Pengaturan; 0 = tanpa garansi
        });

        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('product_name');
            $table->string('serial_number', 60)->nullable()->unique();
            $table->date('starts_at');
            $table->date('expires_at')->index();
            $table->string('status', 20)->default('active'); // active | void
            $table->string('void_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('warranty_id')->nullable()->constrained('warranties')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('product_name');
            $table->string('serial_number', 60)->nullable()->index();
            $table->string('issue_type', 30);
            $table->text('description');
            $table->json('photos')->nullable();
            $table->string('contact_phone', 30);
            $table->string('status', 30)->default('baru')->index();
            $table->boolean('in_warranty')->default(false);
            $table->text('admin_note')->nullable();
            $table->decimal('estimate_cost', 15, 2)->nullable();
            $table->decimal('final_cost', 15, 2)->default(0);
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_ticket_id')->constrained('service_tickets')->cascadeOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_public')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ticket_events');
        Schema::dropIfExists('service_tickets');
        Schema::dropIfExists('warranties');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('warranty_months'));
    }
};
