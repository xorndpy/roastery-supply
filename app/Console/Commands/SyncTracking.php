<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Console\Command;
use Throwable;

class SyncTracking extends Command
{
    protected $signature = 'tracking:sync {--limit=50 : Maksimal resi per proses}';

    protected $description = 'Lacak resi pesanan berstatus dikirim; paket yang sudah diterima otomatis menyelesaikan pesanan';

    public function handle(TrackingService $tracking): int
    {
        if (! $tracking->enabled()) {
            $this->info('RAJAONGKIR_API_KEY kosong: pelacakan otomatis nonaktif.');

            return self::SUCCESS;
        }

        $done = 0;
        Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereHas('order', fn ($q) => $q->where('status', 'dikirim'))
            ->where(fn ($q) => $q->whereNull('tracking_checked_at')->orWhere('tracking_checked_at', '<', now()->subMinutes(25)))
            ->orderBy('tracking_checked_at')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (Shipment $s) use ($tracking, &$done) {
                try {
                    $done += $tracking->sync($s) ? 1 : 0;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Resi diperbarui: {$done}");

        return self::SUCCESS;
    }
}
