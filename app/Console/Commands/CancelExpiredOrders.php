<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class CancelExpiredOrders extends Command
{
    protected $signature = 'orders:cancel-expired';

    protected $description = 'Batalkan order menunggu_bayar yang melewati batas 24 jam.';

    public function handle(OrderService $orders): int
    {
        $count = $orders->cancelExpired();
        $this->info("{$count} order kedaluwarsa dibatalkan.");

        return self::SUCCESS;
    }
}
