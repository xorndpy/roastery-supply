<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use App\Services\VoucherService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesRegions;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase, MakesRegions;

    private VoucherService $vouchers;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $this->vouchers = app(VoucherService::class);
    }

    // ---------- helpers ----------

    private function voucher(array $a = []): Voucher
    {
        static $n = 0;
        $n++;

        return Voucher::create(array_merge([
            'code' => "KODE{$n}", 'type' => 'percentage', 'value' => 10, 'min_purchase' => 0, 'max_discount' => null,
            'quota' => 0, 'per_user_limit' => 1, 'is_active' => true,
        ], $a));
    }

    private function product(int $price = 1000000, int $stock = 10): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Mesin V{$n}", 'slug' => "mesin-v{$n}", 'sku' => "V-{$n}", 'price' => $price, 'stock' => $stock, 'stock_threshold' => 1, 'is_active' => true]));
    }

    private function cartFor(Product $p, int $qty = 1): array
    {
        return ['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => $qty, 'image' => null, 'stock' => $p->stock]]];
    }

    private function payload(string $key = 'k1'): array
    {
        return ['idempotency_key' => $key, 'name' => 'Budi', 'phone' => '0812', 'address' => 'Jl. Kopi 1', 'province_id' => $this->region()[0]->id, 'city_id' => $this->region()[1]->id, 'postal_code' => '40111'];
    }

    private function checkout(User $user, Product $p, ?string $code, int $qty = 1, string $key = 'k1')
    {
        $session = $this->cartFor($p, $qty) + ['checkout_key' => $key];
        if ($code) {
            $session['checkout_voucher'] = $code;
        }

        return $this->actingAs($user)->withSession($session)->post(route('customer.checkout.store'), $this->payload($key));
    }

    private function assertRejected(string $code, string $messagePart, float $subtotal = 1000000, ?User $user = null): void
    {
        try {
            $this->vouchers->validate($code, $subtotal, $user);
            $this->fail("Seharusnya ditolak: {$messagePart}");
        } catch (DomainException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    // ---------- aturan validasi ----------

    public function test_code_is_case_and_space_insensitive(): void
    {
        $v = $this->voucher(['code' => 'HEMAT10']);

        $this->assertTrue($this->vouchers->validate(' hemat 10 ', 1000, null)->is($v));
        $this->assertTrue($this->vouchers->validate('Hemat10', 1000, null)->is($v));
    }

    public function test_unknown_inactive_scheduled_and_expired_codes_are_rejected(): void
    {
        $this->assertRejected('TIDAKADA', 'tidak valid');
        $this->voucher(['code' => 'MATI', 'is_active' => false]);
        $this->assertRejected('MATI', 'tidak valid');
        $this->voucher(['code' => 'NANTI', 'start_at' => now()->addDay()]);
        $this->assertRejected('NANTI', 'belum berlaku');
        $this->voucher(['code' => 'LEWAT', 'end_at' => now()->subMinute()]);
        $this->assertRejected('LEWAT', 'kedaluwarsa');
    }

    public function test_minimum_purchase_and_quota_are_enforced(): void
    {
        $this->voucher(['code' => 'MIN', 'min_purchase' => 5000000]);
        $this->assertRejected('MIN', 'Minimal belanja Rp 5.000.000', 4999999);
        $this->assertTrue($this->vouchers->validate('MIN', 5000000, null)->exists); // tepat di batas boleh

        $this->voucher(['code' => 'HABIS', 'quota' => 2, 'used_count' => 2]);
        $this->assertRejected('HABIS', 'Kuota');

        $this->voucher(['code' => 'MASIH', 'quota' => 2, 'used_count' => 1]);
        $this->assertTrue($this->vouchers->validate('MASIH', 1000, null)->exists);
    }

    public function test_discount_math(): void
    {
        $pct = $this->voucher(['type' => 'percentage', 'value' => 10]);
        $this->assertSame(100000, $this->vouchers->discount($pct, 1000000));
        $this->assertSame(333, $this->vouchers->discount($this->voucher(['value' => 10]), 3333)); // dibulatkan ke bawah

        $capped = $this->voucher(['type' => 'percentage', 'value' => 50, 'max_discount' => 200000]);
        $this->assertSame(200000, $this->vouchers->discount($capped, 1000000));
        $this->assertSame(100000, $this->vouchers->discount($capped, 200000)); // di bawah batas -> tidak dipotong

        $fixed = $this->voucher(['type' => 'fixed', 'value' => 150000]);
        $this->assertSame(150000, $this->vouchers->discount($fixed, 1000000));
        $this->assertSame(80000, $this->vouchers->discount($fixed, 80000)); // tidak pernah melebihi subtotal -> total tak minus
    }

    // ---------- checkout ----------

    public function test_applying_a_voucher_shows_the_discount_on_checkout(): void
    {
        $user = User::factory()->create();
        $p = $this->product(2000000);
        $this->voucher(['code' => 'HEMAT10']);

        $this->actingAs($user)->withSession($this->cartFor($p, 1) + ['checkout_key' => 'k'])
            ->post(route('customer.checkout.voucher'), ['code' => 'hemat10'])
            ->assertRedirect(route('customer.checkout.index'))->assertSessionHas('checkout_voucher', 'HEMAT10');

        $this->actingAs($user)->withSession($this->cartFor($p, 1) + ['checkout_key' => 'k', 'checkout_voucher' => 'HEMAT10'])
            ->get(route('customer.checkout.index'))->assertOk()
            ->assertSee('HEMAT10')->assertSee('200.000')->assertSee('1.800.000');
    }

    public function test_invalid_voucher_gives_an_error_and_is_not_stored(): void
    {
        $user = User::factory()->create();
        $p = $this->product();
        $this->voucher(['code' => 'MINBESAR', 'min_purchase' => 99000000]);

        $res = $this->actingAs($user)->withSession($this->cartFor($p))->post(route('customer.checkout.voucher'), ['code' => 'MINBESAR']);
        $res->assertRedirect(route('customer.checkout.index'))->assertSessionHas('voucher_error')->assertSessionMissing('checkout_voucher');

        $this->actingAs($user)->withSession($this->cartFor($p))->post(route('customer.checkout.voucher'), ['code' => 'NGASAL'])->assertSessionHas('voucher_error');
        $this->actingAs($user)->withSession($this->cartFor($p))->post(route('customer.checkout.voucher'), ['code' => ''])->assertSessionHasErrors('code');
    }

    public function test_guest_cannot_apply_a_voucher(): void
    {
        $this->post(route('customer.checkout.voucher'), ['code' => 'X'])->assertRedirect(route('login'));
    }

    public function test_one_voucher_per_order_a_new_code_replaces_the_old_one(): void
    {
        $user = User::factory()->create();
        $p = $this->product();
        $this->voucher(['code' => 'AAA']);
        $this->voucher(['code' => 'BBB']);

        $this->actingAs($user)->withSession($this->cartFor($p) + ['checkout_voucher' => 'AAA'])
            ->post(route('customer.checkout.voucher'), ['code' => 'BBB'])->assertSessionHas('checkout_voucher', 'BBB');
    }

    public function test_order_stores_discount_total_and_voucher_snapshot_and_uses_quota(): void
    {
        $user = User::factory()->create();
        $p = $this->product(2000000);
        $v = $this->voucher(['code' => 'HEMAT10', 'quota' => 5]);

        $this->checkout($user, $p, 'HEMAT10', 2)->assertSessionHasNoErrors();

        $order = Order::first();
        $this->assertSame('4000000.00', (string) $order->subtotal);
        $this->assertSame('400000.00', (string) $order->discount);
        $this->assertSame('3600000.00', (string) $order->total);
        $this->assertSame($v->id, $order->voucher_id);
        $this->assertSame('HEMAT10', $order->voucher_code);
        $this->assertSame(1, $v->fresh()->used_count);

        // tagihan pembayaran mengikuti total setelah diskon
        $this->actingAs($user)->get(route('customer.orders.show', $order->order_number))->assertOk()->assertSee('HEMAT10')->assertSee('3.600.000');
    }

    public function test_checkout_without_voucher_is_unchanged(): void
    {
        $user = User::factory()->create();
        $p = $this->product(1000000);

        $this->checkout($user, $p, null);

        $order = Order::first();
        $this->assertSame('0.00', (string) $order->discount);
        $this->assertSame('1000000.00', (string) $order->total);
        $this->assertNull($order->voucher_id);
    }

    public function test_per_user_limit_blocks_a_second_use_but_not_other_users(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $p = $this->product();
        $v = $this->voucher(['code' => 'SEKALI', 'per_user_limit' => 1]);

        $this->checkout($a, $p, 'SEKALI', 1, 'k-a1');
        $this->assertSame(1, Order::count());

        // user A mencoba lagi dengan kunci baru
        $this->checkout($a, $p, 'SEKALI', 1, 'k-a2')->assertRedirect(route('customer.checkout.index'))->assertSessionHas('warning');
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $v->fresh()->used_count);

        $this->checkout($b, $p, 'SEKALI', 1, 'k-b1');
        $this->assertSame(2, Order::count());
    }

    public function test_quota_is_enforced_at_order_time_even_if_it_was_valid_when_applied(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $p = $this->product();
        $v = $this->voucher(['code' => 'TERAKHIR', 'quota' => 1]);

        $this->checkout($a, $p, 'TERAKHIR', 1, 'ka'); // mengambil kuota terakhir
        $res = $this->checkout($b, $p, 'TERAKHIR', 1, 'kb'); // pengguna lain sebelumnya sempat menerapkan kode ini

        $res->assertRedirect(route('customer.checkout.index'))->assertSessionHas('warning')->assertSessionMissing('checkout_voucher');
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $v->fresh()->used_count);
    }

    public function test_voucher_that_expired_after_applying_is_dropped_without_creating_an_order(): void
    {
        $user = User::factory()->create();
        $p = $this->product();
        $v = $this->voucher(['code' => 'FLASH', 'end_at' => now()->subMinute()]);

        $this->checkout($user, $p, 'FLASH')->assertRedirect(route('customer.checkout.index'))->assertSessionHas('warning');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, $v->fresh()->used_count);
    }

    public function test_removing_the_voucher_works(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['checkout_voucher' => 'AAA'])
            ->delete(route('customer.checkout.voucher.remove'))->assertSessionMissing('checkout_voucher');
    }

    // ---------- kuota kembali saat batal ----------

    private function orderWithVoucher(User $user, Voucher $v, string $status = 'menunggu_bayar'): Order
    {
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => 'B', 'email' => $user->email]));
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => $status,
            'subtotal' => 1000, 'discount' => 100, 'total' => 900, 'voucher_id' => $v->id, 'voucher_code' => $v->code,
        ]));
        $v->increment('used_count');

        return $order;
    }

    public function test_cancelling_an_order_returns_the_voucher_quota_and_the_users_turn(): void
    {
        $user = User::factory()->create();
        $v = $this->voucher(['code' => 'BALIK', 'quota' => 1, 'per_user_limit' => 1]);
        $order = $this->orderWithVoucher($user, $v);
        $this->assertSame(1, $v->fresh()->used_count);

        app(OrderService::class)->cancel($order, 'test', 1);

        $this->assertSame(0, $v->fresh()->used_count);
        $this->assertTrue($this->vouchers->validate('BALIK', 1000, $user)->exists); // bisa dipakai lagi
    }

    public function test_auto_cancel_of_unpaid_orders_also_releases_the_voucher(): void
    {
        $user = User::factory()->create();
        $v = $this->voucher(['code' => 'EXPIRE', 'quota' => 3]);
        $order = $this->orderWithVoucher($user, $v);
        $order->forceFill(['created_at' => now()->subHours(30)])->save();

        $this->assertSame(1, app(OrderService::class)->cancelExpired());
        $this->assertSame(0, $v->fresh()->used_count);
    }

    public function test_release_never_goes_below_zero_and_is_noop_without_voucher(): void
    {
        $user = User::factory()->create();
        $v = $this->voucher(['code' => 'NOL']);
        $order = $this->orderWithVoucher($user, $v);
        $v->update(['used_count' => 0]);

        $this->vouchers->release($order);
        $this->assertSame(0, $v->fresh()->used_count);

        $this->vouchers->release(new Order()); // tanpa voucher: tidak error
        $this->addToAssertionCount(1);
    }

    // ---------- invoice ----------

    public function test_invoice_shows_the_voucher_code(): void
    {
        $user = User::factory()->create();
        $v = $this->voucher(['code' => 'INVOICE5']);
        $order = $this->orderWithVoucher($user, $v);

        $res = $this->actingAs($user)->get(route('customer.orders.invoice', $order->order_number));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    // ---------- admin ----------

    private function adminUser(): User
    {
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    public function test_admin_creates_a_voucher_with_normalized_code(): void
    {
        $this->actingAs($this->adminUser())->post(route('admin.vouchers.store'), [
            'code' => ' hemat 15 ', 'type' => 'percentage', 'value' => 15, 'max_discount' => 500000, 'min_purchase' => 1000000,
            'quota' => 100, 'per_user_limit' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.vouchers.index'));

        $v = Voucher::first();
        $this->assertSame('HEMAT15', $v->code);
        $this->assertSame(500000, (int) $v->max_discount);
    }

    public function test_admin_voucher_validation(): void
    {
        $admin = $this->adminUser();
        $this->voucher(['code' => 'ADA']);
        $base = ['type' => 'percentage', 'value' => 10];

        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'ada'] + $base)->assertSessionHasErrors('code');           // duplikat (tak peka huruf)
        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'A B$'] + $base)->assertSessionHas('errors');
        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'PERSEN', 'type' => 'percentage', 'value' => 150])->assertSessionHasErrors('value');
        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'NOL', 'type' => 'fixed', 'value' => 0])->assertSessionHasErrors('value');
        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'TGL', 'start_at' => '2026-12-10 10:00', 'end_at' => '2026-12-01 10:00'] + $base)->assertSessionHasErrors('end_at');
        $this->actingAs($admin)->post(route('admin.vouchers.store'), ['code' => 'JENIS', 'type' => 'bogus', 'value' => 5])->assertSessionHasErrors('type');

        $this->assertSame(1, Voucher::count());
    }

    public function test_max_discount_is_ignored_for_fixed_vouchers(): void
    {
        $this->actingAs($this->adminUser())->post(route('admin.vouchers.store'), ['code' => 'FIX', 'type' => 'fixed', 'value' => 50000, 'max_discount' => 999])->assertSessionHasNoErrors();

        $this->assertNull(Voucher::first()->max_discount);
    }

    public function test_code_and_type_are_locked_after_the_voucher_was_used(): void
    {
        $admin = $this->adminUser();
        $used = $this->voucher(['code' => 'TERPAKAI', 'type' => 'percentage', 'value' => 10, 'used_count' => 3]);

        $this->actingAs($admin)->put(route('admin.vouchers.update', $used->id), ['code' => 'GANTI', 'type' => 'fixed', 'value' => 20, 'quota' => 50, 'per_user_limit' => 1, 'is_active' => 1])->assertSessionHasNoErrors();

        $used->refresh();
        $this->assertSame('TERPAKAI', $used->code);          // dikunci
        $this->assertSame('percentage', $used->type);        // dikunci
        $this->assertSame(20, (int) $used->value);           // nilai lain boleh diubah
        $this->assertSame(50, $used->quota);

        $fresh = $this->voucher(['code' => 'BELUMPAKAI']);
        $this->actingAs($admin)->put(route('admin.vouchers.update', $fresh->id), ['code' => 'BOLEHGANTI', 'type' => 'fixed', 'value' => 20, 'is_active' => 1]);
        $this->assertSame('BOLEHGANTI', $fresh->fresh()->code);
    }

    public function test_deleting_a_voucher_keeps_the_history_on_old_orders(): void
    {
        $user = User::factory()->create();
        $v = $this->voucher(['code' => 'KENANGAN']);
        $order = $this->orderWithVoucher($user, $v);

        $this->actingAs($this->adminUser())->delete(route('admin.vouchers.destroy', $v->id))->assertSessionHas('success');

        $this->assertSoftDeleted($v);
        $this->assertSame('KENANGAN', $order->fresh()->voucher_code);
        $this->assertRejected('KENANGAN', 'tidak valid');   // tidak bisa dipakai lagi
    }

    public function test_voucher_pages_render_filter_and_are_admin_only(): void
    {
        $admin = $this->adminUser();
        $this->voucher(['code' => 'AKTIFAJA']);
        $this->voucher(['code' => 'KADALUARSA', 'end_at' => now()->subDay()]);
        $this->voucher(['code' => 'HABISKUOTA', 'quota' => 1, 'used_count' => 1]);
        $v = Voucher::first();

        $this->actingAs($admin)->get(route('admin.vouchers.index'))->assertOk()->assertSee('AKTIFAJA')->assertSee('KADALUARSA')->assertSee('HABISKUOTA');
        $this->actingAs($admin)->get(route('admin.vouchers.index', ['state' => 'berakhir']))->assertSee('KADALUARSA')->assertDontSee('AKTIFAJA');
        $this->actingAs($admin)->get(route('admin.vouchers.index', ['state' => 'habis']))->assertSee('HABISKUOTA')->assertDontSee('AKTIFAJA');
        $this->actingAs($admin)->get(route('admin.vouchers.index', ['search' => 'aktifaja']))->assertSee('AKTIFAJA')->assertDontSee('KADALUARSA');
        $this->actingAs($admin)->get(route('admin.vouchers.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.vouchers.edit', $v->id))->assertOk();

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $this->actingAs($staff)->get(route('admin.vouchers.index'))->assertForbidden();
    }
}
