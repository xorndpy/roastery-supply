<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Voucher khusus member baru dan daftar voucher yang dipajang ke pelanggan. */
class VoucherAudienceTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $vouchers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vouchers = app(VoucherService::class);
    }

    private function voucher(array $a = []): Voucher
    {
        static $n = 0;
        $n++;

        return Voucher::create(array_merge([
            'code' => "AUD{$n}", 'type' => 'percentage', 'value' => 10, 'min_purchase' => 0, 'max_discount' => null,
            'quota' => 0, 'per_user_limit' => 1, 'is_active' => true,
        ], $a));
    }

    private function orderFor(User $user, string $status = 'menunggu_bayar', ?Voucher $v = null): Order
    {
        $customer = Customer::firstOrCreate(['user_id' => $user->id], ['name' => 'B', 'email' => $user->email]);

        return Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => $status,
            'subtotal' => 1000, 'discount' => 0, 'total' => 1000, 'voucher_id' => $v?->id, 'voucher_code' => $v?->code,
        ]));
    }

    public function test_new_member_voucher_works_for_user_without_orders(): void
    {
        $v = $this->voucher(['audience' => 'new_member']);

        $this->assertSame($v->id, $this->vouchers->validate($v->code, 100000, User::factory()->create())->id);
    }

    public function test_new_member_voucher_is_rejected_after_first_order(): void
    {
        $v = $this->voucher(['audience' => 'new_member']);
        $user = User::factory()->create();
        $this->orderFor($user);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('pesanan pertama');
        $this->vouchers->validate($v->code, 100000, $user);
    }

    public function test_cancelled_orders_do_not_count_so_user_is_still_new(): void
    {
        $v = $this->voucher(['audience' => 'new_member']);
        $user = User::factory()->create();
        $this->orderFor($user, 'batal');

        $this->assertSame($v->id, $this->vouchers->validate($v->code, 100000, $user)->id);
    }

    public function test_guest_cannot_validate_new_member_voucher(): void
    {
        $v = $this->voucher(['audience' => 'new_member']);

        $this->expectException(DomainException::class);
        $this->vouchers->validate($v->code, 100000, null);
    }

    public function test_regular_voucher_ignores_audience_rule(): void
    {
        $v = $this->voucher();
        $user = User::factory()->create();
        $this->orderFor($user);

        $this->assertSame($v->id, $this->vouchers->validate($v->code, 100000, $user)->id);
    }

    public function test_available_list_shows_only_valid_public_vouchers(): void
    {
        $ok = $this->voucher(['code' => 'OKE10']);
        $this->voucher(['code' => 'RAHASIA', 'is_public' => false]);
        $this->voucher(['code' => 'MATI', 'is_active' => false]);
        $this->voucher(['code' => 'LEWAT', 'end_at' => now()->subDay()]);
        $this->voucher(['code' => 'BELUM', 'start_at' => now()->addDay()]);
        $this->voucher(['code' => 'HABIS', 'quota' => 5, 'used_count' => 5]);

        $codes = $this->vouchers->availableFor(User::factory()->create())->pluck('code')->all();

        $this->assertSame([$ok->code], $codes);
    }

    public function test_new_member_voucher_is_hidden_once_user_has_ordered(): void
    {
        $this->voucher(['code' => 'WELCOME', 'audience' => 'new_member']);
        $newbie = User::factory()->create();
        $veteran = User::factory()->create();
        $this->orderFor($veteran);

        $this->assertContains('WELCOME', $this->vouchers->availableFor($newbie)->pluck('code')->all());
        $this->assertNotContains('WELCOME', $this->vouchers->availableFor($veteran)->pluck('code')->all());
    }

    public function test_voucher_already_used_up_by_user_is_hidden(): void
    {
        $v = $this->voucher(['code' => 'SEKALI', 'per_user_limit' => 1]);
        $user = User::factory()->create();
        $this->orderFor($user, 'selesai', $v);

        $this->assertNotContains('SEKALI', $this->vouchers->availableFor($user)->pluck('code')->all());
    }

    public function test_min_purchase_gap_and_ordering(): void
    {
        $this->voucher(['code' => 'JAUH', 'min_purchase' => 5000000]);
        $this->voucher(['code' => 'PAS', 'min_purchase' => 1000000]);

        $list = $this->vouchers->availableFor(User::factory()->create(), 1500000);

        $this->assertSame(['PAS', 'JAUH'], $list->pluck('code')->all());
        $this->assertSame('ready', $list[0]['state']);
        $this->assertNull($list[0]['gap']);
        $this->assertSame('min_purchase', $list[1]['state']);
        $this->assertSame(3500000, $list[1]['gap']);
    }

    public function test_guest_sees_new_member_voucher_as_login_state(): void
    {
        $this->voucher(['code' => 'WELCOME', 'audience' => 'new_member']);

        $this->assertSame('login', $this->vouchers->availableFor(null)->first()['state']);
    }

    public function test_quota_left_is_only_shown_when_really_low(): void
    {
        $this->voucher(['code' => 'LONGGAR', 'quota' => 500, 'used_count' => 10]);
        $this->voucher(['code' => 'TIPIS', 'quota' => 20, 'used_count' => 17]);

        $by = $this->vouchers->availableFor(User::factory()->create())->keyBy('code');

        $this->assertNull($by['LONGGAR']['quota_left']);
        $this->assertSame(3, $by['TIPIS']['quota_left']);
    }

    public function test_benefit_text_is_plain_language(): void
    {
        $this->assertSame('Diskon 10%', $this->voucher(['type' => 'percentage', 'value' => 10])->benefit);
        $this->assertSame('Potongan Rp 100.000', $this->voucher(['type' => 'fixed', 'value' => 100000])->benefit);
    }
}
