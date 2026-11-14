<?php

namespace App\Services;

use App\Models\FinanceClosing;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tutup buku bulanan/periodik. Periode yang ditutup: biaya operasional di dalamnya terkunci, dan angka laba rugi
 * saat penutupan disimpan sebagai snapshot supaya perubahan susulan (mis. refund terlambat) terlihat sebagai selisih.
 * Penutupan harus berurutan tanpa celah; hanya penutupan TERAKHIR yang boleh dibuka kembali.
 */
class FinanceClosingService
{
    public function __construct(private ProfitLossService $report) {}

    public function lockedUntil(): ?string
    {
        $max = FinanceClosing::max('period_end');

        return $max ? Carbon::parse($max)->toDateString() : null;
    }

    public function isLocked(string $date): bool
    {
        $lock = $this->lockedUntil();

        return $lock !== null && $date <= $lock;
    }

    /** Tanggal awal yang disarankan: sehari setelah penutupan terakhir, atau awal bulan data paling awal. */
    public function suggestedStart(): string
    {
        if ($lock = $this->lockedUntil()) {
            return Carbon::parse($lock)->addDay()->toDateString();
        }
        $first = DB::table('orders')->whereNull('deleted_at')->min('created_at');

        return $first ? Carbon::parse($first)->timezone('Asia/Jakarta')->startOfMonth()->toDateString() : now('Asia/Jakarta')->startOfMonth()->toDateString();
    }

    public function close(string $from, string $to, ?string $note, User $by): FinanceClosing
    {
        $today = now('Asia/Jakarta')->toDateString();

        if ($to >= $today) {
            throw new DomainException('Periode yang ditutup harus sudah berakhir (paling lambat kemarin).');
        }
        if ($to < $from) {
            throw new DomainException('Tanggal akhir tidak boleh sebelum tanggal awal.');
        }
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366) {
            throw new DomainException('Satu penutupan maksimal 1 tahun.');
        }
        if ($from !== $this->suggestedStart()) {
            throw new DomainException('Penutupan harus berurutan: tanggal awal harus '.Carbon::parse($this->suggestedStart())->format('d M Y').'.');
        }

        $snapshot = $this->report->report($from, $to);
        unset($snapshot['products']); // cukup angka ringkasan

        return FinanceClosing::create([
            'period_start' => $from, 'period_end' => $to, 'snapshot' => $snapshot,
            'note' => $note, 'closed_by' => $by->id, 'closed_at' => now(),
        ]);
    }

    public function reopen(FinanceClosing $closing, string $reason, User $by): void
    {
        $latest = FinanceClosing::orderByDesc('period_end')->first();
        if (! $latest || $latest->id !== $closing->id) {
            throw new DomainException('Hanya penutupan terakhir yang bisa dibuka kembali.');
        }

        $closing->update(['reopen_reason' => $reason, 'reopened_by' => $by->id]);
        $closing->delete();
    }

    /** Laba bersih sekarang vs snapshot saat penutupan. */
    public function drift(FinanceClosing $closing): array
    {
        $now = $this->report->report($closing->period_start->toDateString(), $closing->period_end->toDateString());
        $snap = $closing->snapshot;

        return [
            'revenue' => round($now['revenue'] - (float) ($snap['revenue'] ?? 0), 2),
            'net_profit' => round($now['net_profit'] - (float) ($snap['net_profit'] ?? 0), 2),
            'now_net_profit' => $now['net_profit'],
        ];
    }
}
