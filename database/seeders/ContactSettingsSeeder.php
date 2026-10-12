<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Mengisi data kontak & sosial media CONTOH (hanya yang masih kosong).
 * Ganti dengan data asli lewat Admin > Pengaturan.
 *
 *   php artisan db:seed --class=ContactSettingsSeeder
 */
class ContactSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $values = [
            'contact_address'  => 'Jl. Contoh Raya No. 12, Jakarta',
            'contact_email'    => 'info@roasterysupply.com',
            'contact_phone'    => '021-0000-0000',
            'contact_whatsapp' => '6281200000000',
            'contact_hours'    => 'Senin – Sabtu, 08.00 – 17.00 WIB',
            'social_instagram' => 'https://instagram.com/roasterysupply',
            'social_tiktok'    => 'https://tiktok.com/@roasterysupply',
            'social_linkedin'  => 'https://linkedin.com/company/roasterysupply',
            'social_facebook'  => 'https://facebook.com/roasterysupply',
        ];

        $stored = Setting::stored();

        foreach ($values as $key => $value) {
            if (filled($stored[$key] ?? null)) {
                continue;
            }

            $group = collect(config('cms.settings'))->search(fn ($fields) => isset($fields[$key])) ?: 'general';
            Setting::set($key, $value, $group);
        }
    }
}
