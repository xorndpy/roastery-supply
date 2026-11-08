<?php

namespace App\Support;

use App\Models\Shipment;

/** Pembantu untuk input kurir & nomor resi. */
class Couriers
{
    /** @return list<string> */
    public static function options(): array
    {
        return config('couriers.list', []);
    }

    /** Tebak kurir dari teks pilihan pelanggan (mis. "JNE REG" -> "JNE"). Null bila tidak cocok. */
    public static function guess(?string $text): ?string
    {
        $text = self::key((string) $text);
        if ($text === '') {
            return null;
        }
        foreach (self::options() as $name) {
            $k = self::key(preg_replace('/\s*\(.*\)$/', '', $name));
            if ($k !== '' && str_contains($text, $k)) {
                return $name;
            }
        }

        return null;
    }

    /** Huruf besar, tanpa spasi (salin-tempel sering membawa spasi/enter). */
    public static function normalizeResi(string $resi): string
    {
        return strtoupper(preg_replace('/\s+/', '', $resi) ?? '');
    }

    public static function validResi(string $resi): bool
    {
        return (bool) preg_match('/^[A-Z0-9\-\/\.]{4,40}$/', $resi);
    }

    /** Nomor pesanan lain yang sudah memakai kombinasi kurir+resi ini, atau null. */
    public static function usedByOtherOrder(string $courier, string $resi, ?int $exceptOrderId): ?string
    {
        $dup = Shipment::with('order')
            ->whereRaw('LOWER(courier) = ?', [mb_strtolower($courier)])
            ->where('tracking_number', $resi)
            ->when($exceptOrderId, fn ($q) => $q->where('order_id', '!=', $exceptOrderId))
            ->first();

        return $dup ? ($dup->order->order_number ?? '#' . $dup->order_id) : null;
    }

    private static function key(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($s)) ?? '';
    }
}
