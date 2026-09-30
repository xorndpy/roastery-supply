<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General
            [
                'key' => 'site_name',
                'value' => 'Roastery Supply',
                'group' => 'general',
            ],
            [
                'key' => 'site_tagline',
                'value' => 'Premium Coffee Machines & Barista Equipments',
                'group' => 'general',
            ],
            [
                'key' => 'site_logo',
                'value' => 'assets/images/logo.png',
                'group' => 'general',
            ],
            [
                'key' => 'site_favicon',
                'value' => 'assets/images/favicon.ico',
                'group' => 'general',
            ],

            // Contact
            [
                'key' => 'contact_phone',
                'value' => '+62 812-3456-7890',
                'group' => 'contact',
            ],
            [
                'key' => 'contact_whatsapp',
                'value' => '+6281234567890',
                'group' => 'contact',
            ],
            [
                'key' => 'contact_email',
                'value' => 'info@roasterysupply.com',
                'group' => 'contact',
            ],
            [
                'key' => 'company_address',
                'value' => 'Jl. Senopati No. 88, Kebayoran Baru, Jakarta Selatan, DKI Jakarta 12190',
                'group' => 'contact',
            ],

            // Social Media
            [
                'key' => 'social_instagram',
                'value' => 'https://instagram.com/roasterysupply',
                'group' => 'social',
            ],
            [
                'key' => 'social_facebook',
                'value' => 'https://facebook.com/roasterysupply',
                'group' => 'social',
            ],
            [
                'key' => 'social_youtube',
                'value' => 'https://youtube.com/@roasterysupply',
                'group' => 'social',
            ],

            // Business & Store Policies
            [
                'key' => 'stock_threshold_default',
                'value' => '5',
                'group' => 'business',
            ],
            [
                'key' => 'payment_expired_hours',
                'value' => '24',
                'group' => 'business',
            ],
            [
                'key' => 'return_max_days',
                'value' => '7',
                'group' => 'business',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'group' => $setting['group'],
                ]
            );
        }
    }
}
