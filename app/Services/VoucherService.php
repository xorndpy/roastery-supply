<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan voucher (PRD: maks 1 per order, tidak bisa digabung, divalidasi ulang saat checkout):
 *  - kode tidak peka huruf besar/kecil
 *  - aktif, dalam masa berlaku, belanja >= min_purchase
 *  - kuota total (0 = tanpa batas) dan batas per user (0 = tanpa batas, default 1)
 *  - audience new_member: hanya untuk user yang belum punya pesanan aktif (pesanan batal tidak dihitung)
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
        if ($voucher->is_new_member_only) {
            if (!$user) {
                throw new DomainException('Masuk atau daftar dulu untuk memakai voucher member baru.');
            }
            if ($this->hasOrdered($user)) {
                throw new DomainException('Voucher ini khusus untuk pesanan pertama.');
            }
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

    /** Sudah pernah punya pesanan yang tidak dibatalkan? (menentukan "member baru") */
    public function hasOrdered(User $user): bool
    {
        return Order::where('user_id', $user->id)->where('status', '!=', 'batal')->exists();
    }

    /**
     * Voucher yang bisa dilihat pelanggan. Hanya yang saat ini berlaku, masih ada kuota, dan belum habis dipakai user ini.
     * Tiap item memuat teks siap tampil dan `state`: ready (bisa dipakai), min_purchase (kurang belanja, ada `gap`),
     * atau login (voucher member baru dilihat tamu).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function availableFor(?User $user, float|int|string|null $subtotal = null, int $limit = 6): Collection
    {
        $now = now();

        $vouchers = Voucher::query()
            ->where('is_active', true)->where('is_public', true)
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', $now))
            ->where(fn ($q) => $q->where('quota', 0)->orWhereColumn('used_count', '<', 'quota'))
            ->get();

        $ordered = $user ? $this->hasOrdered($user) : false;
        $usedBy = $user
            ? Order::where('user_id', $user->id)->where('status', '!=', 'batal')->whereNotNull('voucher_id')
                ->selectRaw('voucher_id, count(*) as c')->groupBy('voucher_id')->pluck('c', 'voucher_id')
            : collect();

        $items = $vouchers->reject(function (Voucher $v) use ($ordered, $usedBy) {
            if ($v->is_new_member_only && $ordered) {
                return true;
            }

            return $v->per_user_limit > 0 && (int) ($usedBy[$v->id] ?? 0) >= $v->per_user_limit;
        })->map(function (Voucher $v) use ($user, $subtotal) {
            $gap = $subtotal !== null && (float) $subtotal < (float) $v->min_purchase
                ? (int) ceil((float) $v->min_purchase - (float) $subtotal) : null;

            $terms = [];
            if ($v->type === 'percentage' && (float) $v->max_discount > 0) {
                $terms[] = 'Maks. potongan Rp ' . number_format($v->max_discount, 0, ',', '.');
            }
            if ((float) $v->min_purchase > 0) {
                $terms[] = 'Min. belanja Rp ' . number_format($v->min_purchase, 0, ',', '.');
            }
            if ($v->is_new_member_only) {
                $terms[] = 'Hanya untuk pesanan pertama';
            }

            // Kuota hanya ditonjolkan kalau benar-benar menipis, supaya kelangkaan yang tampil selalu nyata.
            $left = $v->quota > 0 ? $v->quota - $v->used_count : null;

            return [
                'code' => $v->code,
                'benefit' => $v->benefit,
                'description' => $v->description,
                'is_new_member' => $v->is_new_member_only,
                'terms' => $terms,
                'valid_until' => $v->end_at,
                'quota_left' => $left !== null && $left <= 10 ? $left : null,
                'gap' => $gap,
                'state' => $v->is_new_member_only && !$user ? 'login' : ($gap !== null ? 'min_purchase' : 'ready'),
            ];
        });

        $rank = ['ready' => 0, 'min_purchase' => 1, 'login' => 2];

        return $items->sortBy([
            fn ($a, $b) => $rank[$a['state']] <=> $rank[$b['state']],
            fn ($a, $b) => $b['is_new_member'] <=> $a['is_new_member'],
            fn ($a, $b) => ($a['valid_until']?->timestamp ?? PHP_INT_MAX) <=> ($b['valid_until']?->timestamp ?? PHP_INT_MAX),
        ])->take($limit)->values();
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
