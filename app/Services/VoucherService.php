<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Aturan voucher (PRD: maks 1 per order, tidak bisa digabung, divalidasi ulang saat checkout):
 *  - kode tidak peka huruf besar/kecil
 *  - aktif, dalam masa berlaku, belanja >= min_purchase
 *  - kuota total (0 = tanpa batas) dan batas per user (0 = tanpa batas, default 1)
 *  - persen: diskon dibatasi max_discount; nominal: tidak pernah melebihi subtotal
 *  - kuota ikut terpakai saat order dibuat, dan DIKEMBALIKAN bila order batal
 */
class VoucherService
{
    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', $code));
    }

    /**
     * Validasi voucher untuk subtotal tertentu. Melempar DomainException berisi alasan yang bisa dibaca customer.
     */
    public function validate(string $code, float|int|string $subtotal, ?User $user, ?Voucher $locked = null): Voucher
    {
        $voucher = $locked ?? Voucher::where('code', self::normalize($code))->first();

        if (!$voucher || !$voucher->is_active) {
            throw new DomainException('Kode voucher tidak valid.');
        }
        if ($voucher->start_at && $voucher->start_at->isFuture()) {
            throw new DomainException('Voucher belum berlaku. Mulai ' . $voucher->start_at->translatedFormat('d M Y H:i') . '.');
        }
        if ($voucher->end_at && $voucher->end_at->isPast()) {
            throw new DomainException('Voucher sudah kedaluwarsa.');
        }
        if ($voucher->quota > 0 && $voucher->used_count >= $voucher->quota) {
            throw new DomainException('Kuota voucher sudah habis.');
        }
        if ((float) $subtotal < (float) $voucher->min_purchase) {
            throw new DomainException('Minimal belanja Rp ' . number_format($voucher->min_purchase, 0, ',', '.') . ' untuk voucher ini.');
        }
        if ($user && $voucher->per_user_limit > 0) {
            $used = Order::where('user_id', $user->id)->where('voucher_id', $voucher->id)->where('status', '!=', 'batal')->count();
            if ($used >= $voucher->per_user_limit) {
                throw new DomainException('Kamu sudah mencapai batas pemakaian voucher ini.');
            }
        }

        return $voucher;
    }

    public function discount(Voucher $voucher, float|int|string $subtotal): int
    {
        $subtotal = (float) $subtotal;

        $discount = $voucher->type === 'percentage'
            ? $subtotal * (float) $voucher->value / 100
            : (float) $voucher->value;

        if ($voucher->type === 'percentage' && $voucher->max_discount !== null && (float) $voucher->max_discount > 0) {
            $discount = min($discount, (float) $voucher->max_discount);
        }

        return (int) floor(max(0, min($discount, $subtotal)));
    }

    /**
     * Pakai voucher saat order dibuat: kunci baris, validasi ulang dengan data TERBARU, lalu tambah used_count.
     * Harus dipanggil di dalam transaksi pembuatan order.
     *
     * @return array{voucher: Voucher, discount: int}
     */
    public function reserve(string $code, float|int|string $subtotal, User $user): array
    {
        $found = Voucher::where('code', self::normalize($code))->first();
        if (!$found) {
            throw new DomainException('Kode voucher tidak valid.');
        }

        $voucher = Voucher::whereKey($found->id)->lockForUpdate()->firstOrFail();
        $this->validate($code, $subtotal, $user, $voucher);

        $voucher->increment('used_count');

        return ['voucher' => $voucher, 'discount' => $this->discount($voucher, $subtotal)];
    }

    /** Kembalikan kuota saat order batal. Aman dipanggil di dalam transaksi pembatalan. */
    public function release(Order $order): void
    {
        if (!$order->voucher_id) {
            return;
        }

        Voucher::withTrashed()->whereKey($order->voucher_id)->where('used_count', '>', 0)->decrement('used_count');
    }
}
