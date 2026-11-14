<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim WhatsApp lewat Fonnte (POST https://api.fonnte.com/send, header Authorization: TOKEN,
 * form: target, message, countryCode). Aktif hanya bila WHATSAPP_TOKEN diisi DAN Pengaturan > WhatsApp > Aktif.
 * Kegagalan tidak pernah dilempar ke pemanggil: WhatsApp adalah pemberitahuan tambahan, bukan bagian transaksi.
 */
class WhatsAppService
{
    public function enabled(): bool
    {
        return (string) config('services.whatsapp.token') !== '' && (string) Setting::get('wa_enabled') === '1';
    }

    /** 0812-3456-789 / +62 812... / 812... -> 62812...; null bila tidak masuk akal. */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return str_starts_with($digits, '62') && strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    public function send(?string $to, string $message): bool
    {
        if (! $this->enabled() || ! ($target = self::normalize($to))) {
            return false;
        }

        try {
            $res = Http::asForm()->timeout(10)
                ->withHeaders(['Authorization' => (string) config('services.whatsapp.token')])
                ->post((string) config('services.whatsapp.url'), ['target' => $target, 'message' => $message, 'countryCode' => '62']);

            $ok = $res->successful() && $res->json('status') === true;
            if (! $ok) {
                Log::warning('WhatsApp gagal terkirim', ['target' => substr($target, 0, 5).'***', 'status' => $res->status(), 'reason' => $res->json('reason')]);
            }

            return $ok;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function toStore(string $message): bool
    {
        return $this->send(Setting::get('wa_store_number'), $message);
    }
}
