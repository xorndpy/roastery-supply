<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Pengaturan awal. Aman dijalankan berulang: tidak menimpa yang sudah diubah admin.
 * Kontak, rekening, dan sosmed sengaja KOSONG: isi lewat Admin > Pengaturan agar tidak ada
 * data karangan (telepon/alamat palsu) yang tampil di website.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('cms.settings') as $group => $fields) {
            foreach ($fields as $key => $def) {
                if (!isset($def['default'])) {
                    continue;
                }
                if (Setting::withTrashed()->where('key', $key)->doesntExist()) {
                    Setting::set($key, $def['default'], $group);
                }
            }
        }
    }
}
