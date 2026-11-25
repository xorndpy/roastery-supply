<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus kolom teks customers.city dan customers.province (duplikat dari city_id / province_id).
 * Pelanggan lama diselamatkan dulu: teks dicocokkan ke tabel cities/provinces untuk mengisi city_id/province_id.
 * Yang tidak bisa dicocokkan dipindahkan ke kolom address supaya tidak ada data yang hilang.
 */
return new class extends Migration
{
    private function norm(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = preg_replace('/^(kota administrasi|kabupaten administrasi|kota|kabupaten|kab\.?|kota adm\.?)\s+/u', '', $s);
        $s = preg_replace('/^(provinsi|prov\.?|daerah khusus ibukota|daerah istimewa|d\.?i\.?)\s+/u', '', $s);

        return preg_replace('/\s+/u', ' ', $s);
    }

    public function up(): void
    {
        if (Schema::hasColumn('customers', 'city') || Schema::hasColumn('customers', 'province')) {
            $this->backfill();
        }

        Schema::table('customers', function (Blueprint $table) {
            foreach (['city', 'province'] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function backfill(): void
    {
        $hasCity = Schema::hasColumn('customers', 'city');
        $hasProv = Schema::hasColumn('customers', 'province');

        $provinces = DB::table('provinces')->get(['id', 'name']);
        $cities = DB::table('cities')->get(['id', 'name', 'province_id']);
        $provByNorm = $provinces->groupBy(fn ($p) => $this->norm($p->name));
        $cityById = $cities->keyBy('id');
        $cityByNorm = $cities->groupBy(fn ($c) => $this->norm($c->name));

        $rows = DB::table('customers')->get();
        $fixed = 0;
        $moved = 0;

        foreach ($rows as $row) {
            $cityText = $hasCity ? trim((string) $row->city) : '';
            $provText = $hasProv ? trim((string) $row->province) : '';
            $cityId = $row->city_id;
            $provId = $row->province_id;
            $update = [];

            // 1) Provinsi dari teks
            if (! $provId && $provText !== '') {
                $match = $provByNorm->get($this->norm($provText));
                if ($match && $match->count() === 1) {
                    $provId = $match->first()->id;
                }
            }

            // 2) Kota dari teks (utamakan yang cocok dengan provinsi bila ada)
            if (! $cityId && $cityText !== '') {
                $cands = $cityByNorm->get($this->norm($cityText), collect());
                if ($provId && $cands->count() > 1) {
                    $cands = $cands->where('province_id', $provId);
                }
                if ($cands->count() === 1) {
                    $cityId = $cands->first()->id;
                }
            }

            // 3) Provinsi dari kota
            if ($cityId && ! $provId && isset($cityById[$cityId])) {
                $provId = $cityById[$cityId]->province_id;
            }

            if ($cityId !== $row->city_id) {
                $update['city_id'] = $cityId;
            }
            if ($provId !== $row->province_id) {
                $update['province_id'] = $provId;
            }

            // 4) Teks yang tetap tidak cocok: pindahkan ke alamat agar tidak hilang
            $leftover = [];
            if (! $cityId && $cityText !== '') {
                $leftover[] = $cityText;
            }
            if (! $provId && $provText !== '') {
                $leftover[] = $provText;
            }
            if ($leftover) {
                $extra = implode(', ', $leftover);
                $addr = trim((string) $row->address);
                if ($addr === '' || ! str_contains(mb_strtolower($addr), mb_strtolower($extra))) {
                    $update['address'] = $addr === '' ? $extra : $addr . ', ' . $extra;
                    $moved++;
                    Log::warning("customers #{$row->id}: wilayah '{$extra}' tidak cocok dengan tabel cities/provinces, dipindah ke address.");
                }
            }

            if ($update) {
                DB::table('customers')->where('id', $row->id)->update($update);
                $fixed++;
            }
        }

        Log::info("Migrasi customers: {$fixed} baris diperbarui, {$moved} teks wilayah dipindah ke address.");
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('customers', 'province')) {
                $table->string('province')->nullable()->after('city');
            }
        });

        DB::table('customers')->orderBy('id')->each(function ($row) {
            DB::table('customers')->where('id', $row->id)->update([
                'city' => $row->city_id ? DB::table('cities')->where('id', $row->city_id)->value('name') : null,
                'province' => $row->province_id ? DB::table('provinces')->where('id', $row->province_id)->value('name') : null,
            ]);
        });
    }
};
