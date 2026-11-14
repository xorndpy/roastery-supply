<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\WarrantyService;
use Illuminate\Console\Command;

class BackfillWarranties extends Command
{
    protected $signature = 'warranty:backfill';

    protected $description = 'Terbitkan garansi untuk pesanan yang sudah selesai sebelum fitur garansi ada (aman diulang)';

    public function handle(WarrantyService $warranties): int
    {
        $created = 0;
        Order::where('status', 'selesai')->orderBy('id')->chunkById(100, function ($orders) use ($warranties, &$created) {
            foreach ($orders as $order) {
                $created += $warranties->issueFor($order);
            }
        });

        $this->info("Garansi dibuat: {$created}");

        return self::SUCCESS;
    }
}
