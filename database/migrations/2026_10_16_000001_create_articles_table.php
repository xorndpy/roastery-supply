<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 190)->unique();
            $table->string('category', 40)->index();
            $table->string('excerpt', 300)->nullable();
            $table->longText('body');                          // Markdown
            $table->string('cover_image')->nullable();
            $table->string('video_url', 300)->nullable();       // YouTube / Vimeo
            $table->string('author_name', 80)->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('is_featured')->default(false);     // tampil di slider halaman Artikel
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
