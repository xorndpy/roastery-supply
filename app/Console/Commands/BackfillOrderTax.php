<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\ProfitLossService;
use Illuminate\Console\Command;

/**
 * Isi snapshot PPN (tax_rate, tax_amount) untuk pesanan yang dibuat sebelum PPN diaktifkan.
 * Harga sudah termasuk PPN, jadi total pesanan tidak berubah; hanya rincian PPN di invoice yang muncul.
 */
class BackfillOrderTax extends Command
{
    protected $signature = 'orders:backfill-ppn {--all : Termasuk pesanan yang sudah dibayar/selesai} {--dry-run : Hanya tampilkan, tidak menyimpan}';

    protected $description = 'Isi snapshot PPN pada pesanan lama yang tarifnya masih kosong atau 0 (pakai tarif pengaturan saat ini)';

    public function handle(ProfitLossService $pl): int
    {
        $rate = $pl->taxRate();
        if (! $rate) {
            $this->error('PPN belum aktif. Aktifkan di Admin > Pengaturan > Keuangan & Invoice dulu.');

            return self::FAILURE;
        }

        $query = Order::query()->where(fn ($q) => $q->whereNull('tax_rate')->orWhere('tax_rate', 0));
        if (! $this->option('all')) {
            $query->where('status', 'menunggu_bayar');
        }

        $count = 0;
        $query->each(function (Order $order) use ($rate, &$count) {
            $amount = round(((float) $order->subtotal - (float) $order->discount) * $rate / (100 + $rate), 2);
            $this->line("{$order->order_number}: PPN {$rate}% = Rp " . number_format($amount, 0, ',', '.'));

            if (! $this->option('dry-run')) {
                $order->forceFill(['tax_rate' => $rate, 'tax_amount' => $amount])->saveQuietly();
            }
            $count++;
        });

        $this->info(($this->option('dry-run') ? '[dry-run] ' : '') . "{$count} pesanan " . ($this->option('dry-run') ? 'akan diperbarui.' : 'diperbarui.'));

        return self::SUCCESS;
    }
}
