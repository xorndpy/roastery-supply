<?php

namespace Tests\Unit;

use App\Services\PurchaseService;
use PHPUnit\Framework\TestCase;

class PurchaseCostTest extends TestCase
{
    public function test_hpp_pertama_memakai_harga_beli(): void
    {
        $this->assertSame(48000.0, PurchaseService::weightedCost(0, null, 2, 48000.0));
        $this->assertSame(48000.0, PurchaseService::weightedCost(5, null, 2, 48000.0));
    }

    public function test_stok_kosong_atau_negatif_mengabaikan_hpp_lama(): void
    {
        $this->assertSame(50000.0, PurchaseService::weightedCost(0, 99000.0, 3, 50000.0));
        $this->assertSame(50000.0, PurchaseService::weightedCost(-2, 99000.0, 3, 50000.0));
    }

    public function test_rata_rata_tertimbang(): void
    {
        // 10 unit @ 100.000 + 10 unit @ 120.000 = 20 unit @ 110.000
        $this->assertSame(110000.0, PurchaseService::weightedCost(10, 100000.0, 10, 120000.0));
        // 3 unit @ 100.000 + 1 unit @ 200.000 = 4 unit @ 125.000
        $this->assertSame(125000.0, PurchaseService::weightedCost(3, 100000.0, 1, 200000.0));
    }
}
