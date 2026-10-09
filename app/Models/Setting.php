<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /** @var array<string,?string>|null Cache per-request: 1 query untuk semua pengaturan. */
    private static ?array $memo = null;

    protected static function booted(): void
    {
        $reset = fn () => self::$memo = null;
        static::saved($reset);
        static::deleted($reset);
    }

    /** Semua nilai tersimpan (key => value). */
    public static function stored(): array
    {
        return self::$memo ??= static::query()->pluck('value', 'key')->all();
    }

    public static function forgetMemo(): void
    {
        self::$memo = null;
    }

    /** Ambil satu nilai. Kosong/belum diisi -> $default, lalu default dari config/cms.php. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::stored()[$key] ?? null;

        if (filled($value)) {
            return $value;
        }

        return $default ?? static::defaults()[$key] ?? null;
    }

    /** Semua key pengaturan dengan nilai (atau default) - dipakai view lewat variabel $site. */
    public static function site(): array
    {
        $site = [];
        foreach (static::definitions() as $key => $_) {
            $site[$key] = static::get($key);
        }

        return $site;
    }

    /** @return array<string,array> key => definisi, tanpa pengelompokan */
    public static function definitions(): array
    {
        $flat = [];
        foreach (config('cms.settings') as $fields) {
            $flat += $fields;
        }

        return $flat;
    }

    private static function defaults(): array
    {
        return collect(static::definitions())->map(fn ($d) => $d['default'] ?? null)->all();
    }

    /**
     * Set or update a setting value.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }
}
