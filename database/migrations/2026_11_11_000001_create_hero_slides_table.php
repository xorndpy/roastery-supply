<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_HEADER_KEYS = ['header_bg_katalog', 'header_bg_kontak', 'header_bg_faq', 'header_bg_cabang'];

    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Foto hero lama (satu foto) menjadi slide pertama.
        $hero = DB::table('settings')->where('key', 'hero_bg')->whereNotNull('value')->where('value', '!=', '')->value('value');
        if ($hero) {
            DB::table('hero_slides')->insert([
                'image' => $hero, 'sort_order' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('settings')->where('key', 'hero_bg')->delete();

        // Foto header per halaman dilebur: bila foto umum kosong, pakai foto khusus pertama yang ada.
        $stored = DB::table('settings')->whereIn('key', array_merge(['header_bg'], self::OLD_HEADER_KEYS))->pluck('value', 'key');
        if (empty($stored['header_bg'])) {
            foreach (self::OLD_HEADER_KEYS as $key) {
                if (!empty($stored[$key])) {
                    DB::table('settings')->updateOrInsert(
                        ['key' => 'header_bg'],
                        ['value' => $stored[$key], 'group' => 'Tampilan', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null]
                    );
                    break;
                }
            }
        }
        DB::table('settings')->whereIn('key', self::OLD_HEADER_KEYS)->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
