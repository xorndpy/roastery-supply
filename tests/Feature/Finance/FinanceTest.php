<?php

namespace Tests\Feature\Finance;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\User;
use App\Services\ProfitLossService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    // ---------- helpers ----------

    private function userAs(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function product(?float $cost = 600000, float $price = 1000000): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create([
            'name' => "Grinder F{$n}", 'slug' => "grinder-f{$n}", 'sku' => "F-{$n}", 'price' => $price, 'cost_price' => $cost,
            'stock' => 50, 'stock_threshold' => 1, 'is_active' => true,
        ]));
    }

    /** Pesanan 1 produk. createdAtUtc dalam UTC. */
    private function order(Product $p, int $qty, string $status = 'selesai', $itemCost = 'same', float $discount = 0, string $createdAtUtc = '2026-11-10 05:00:00'): Order
    {
        $user = User::factory()->create();
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]));
        $subtotal = $p->price * $qty;

        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => $status,
            'subtotal' => $subtotal, 'discount' => $discount, 'shipping_cost' => 50000, 'total' => $subtotal - $discount + 50000,
            'shipping_name' => 'Budi', 'created_at' => $createdAtUtc, 'updated_at' => $createdAtUtc,
        ]));
        OrderItem::unguarded(fn () => OrderItem::create([
            'order_id' => $order->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku,
            'price' => $p->price, 'cost_price' => $itemCost === 'same' ? $p->cost_price : $itemCost, 'quantity' => $qty, 'subtotal' => $subtotal,
        ]));

        return $order;
    }

    private function expense(string $date, float $amount, string $cat = 'sewa'): Expense
    {
        return Expense::create(['expense_date' => $date, 'category' => $cat, 'description' => 'Biaya uji', 'amount' => $amount]);
    }

    // ================= akses & role =================

    public function test_keuangan_role_reaches_finance_pages_but_not_orders_or_stock_actions(): void
    {
        $k = $this->userAs('keuangan');

        foreach (['admin.finance.profit', 'admin.finance.costs', 'admin.expenses.index', 'admin.expenses.create'] as $route) {
            $this->actingAs($k)->get(route($route))->assertOk();
        }
        $this->actingAs($k)->get(route('admin.finance.profit.export'))->assertOk();

        // bukan wewenang keuangan
        $this->actingAs($k)->get(route('admin.stock.index'))->assertForbidden();
        $this->actingAs($k)->get(route('admin.users.index'))->assertForbidden();
        $this->assertFalse($k->can('verify_payments'));
        $this->assertFalse($k->can('manage_stock'));
        $this->assertTrue($k->can('view_finance'));
    }

    public function test_other_roles_cannot_open_finance_pages(): void
    {
        foreach (['gudang', 'staff', 'customer'] as $role) {
            $u = $this->userAs($role);
            foreach (['admin.finance.profit', 'admin.finance.costs', 'admin.expenses.index'] as $route) {
                $this->actingAs($u)->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.finance.profit'))->assertRedirect(route('login'));
        $this->get(route('admin.expenses.index'))->assertRedirect(route('login'));
    }

    public function test_admin_and_super_admin_can_open_finance_pages(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $this->actingAs($this->userAs($role))->get(route('admin.finance.profit'))->assertOk();
        }
    }

    public function test_sidebar_shows_finance_menu_only_to_allowed_roles(): void
    {
        $this->actingAs($this->userAs('keuangan'))->get(route('admin.finance.profit'))
            ->assertSee('Laba Rugi')->assertSee('Biaya Operasional')->assertSee('HPP Produk');

        $this->actingAs($this->userAs('staff'))->get(route('admin.dashboard'))
            ->assertDontSee('HPP Produk')->assertDontSee('Biaya Operasional');
    }

    // ================= laba rugi =================

    public function test_profit_loss_math(): void
    {
        $p = $this->product(cost: 600000, price: 1000000);

        // terbayar: 3 unit, diskon 100rb -> penjualan neto 2.900.000, HPP 1.800.000
        $this->order($p, 3, 'selesai', 'same', 100000);
        // status belum dibayar / batal tidak dihitung
        $this->order($p, 5, 'menunggu_pembayaran');
        $this->order($p, 5, 'dibatalkan');
        // di luar periode
        $this->order($p, 9, 'selesai', 'same', 0, '2026-10-01 05:00:00');

        $this->expense('2026-11-05', 400000, 'sewa');
        $this->expense('2026-11-06', 100000, 'utilitas');
        $this->expense('2026-10-20', 999999, 'sewa'); // di luar periode

        $r = app(ProfitLossService::class)->report('2026-11-01', '2026-11-30');

        $this->assertSame(1, $r['order_count']);
        $this->assertEquals(3000000, $r['gross_sales']);
        $this->assertEquals(100000, $r['discount']);
        $this->assertEquals(2900000, $r['net_sales']);
        $this->assertEquals(2900000, $r['revenue']);
        $this->assertEquals(1800000, $r['cogs']);
        $this->assertEquals(1100000, $r['gross_profit']);
        $this->assertEquals(500000, $r['expenses_total']);
        $this->assertEquals(600000, $r['net_profit']);
        $this->assertEquals(37.9, $r['gross_margin']);
        $this->assertSame(0, $r['missing_cost_qty']);
        $this->assertEquals(['sewa' => 400000.0, 'utilitas' => 100000.0], $r['expenses_by_category']);
        $this->assertCount(1, $r['products']);
        $this->assertSame(3, $r['products'][0]['qty']);
    }

    public function test_approved_refund_reduces_revenue_but_pending_does_not(): void
    {
        $p = $this->product();
        $order = $this->order($p, 2);
        $order2 = $this->order($p, 1);

        ReturnOrder::unguarded(fn () => ReturnOrder::create([
            'order_id' => $order->id, 'reason' => 'Rusak total saat tiba.', 'reason_type' => 'rusak', 'evidence' => ['returns/x.jpg'],
            'status' => 'approved', 'refund_amount' => 500000, 'approved_at' => '2026-11-11 03:00:00',
            'refund_bank' => 'BCA', 'refund_account_number' => '123456', 'refund_account_name' => 'Budi',
        ]));
        ReturnOrder::unguarded(fn () => ReturnOrder::create([
            'order_id' => $order2->id, 'reason' => 'Rusak total saat tiba.', 'reason_type' => 'rusak', 'evidence' => ['returns/y.jpg'],
            'status' => 'pending', 'refund_amount' => 900000,
            'refund_bank' => 'BCA', 'refund_account_number' => '123456', 'refund_account_name' => 'Budi',
        ]));

        $r = app(ProfitLossService::class)->report('2026-11-01', '2026-11-30');

        $this->assertEquals(500000, $r['refund']);
        $this->assertEquals(3000000 - 500000, $r['revenue']); // 2 pesanan terbayar (2 + 1 unit); hanya refund approved yang mengurangi
    }

    public function test_missing_cost_is_reported_not_hidden(): void
    {
        $p = $this->product(cost: null);
        $this->order($p, 4, 'selesai', null);

        $r = app(ProfitLossService::class)->report('2026-11-01', '2026-11-30');

        $this->assertEquals(0, $r['cogs']);
        $this->assertSame(4, $r['missing_cost_qty']);
        $this->assertSame(1, $r['missing_cost_products']);

        $this->actingAs($this->userAs('keuangan'))->get(route('admin.finance.profit', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()->assertSee('belum punya HPP')->assertSee('HPP kosong');
    }

    public function test_period_uses_wib_day_boundaries(): void
    {
        $p = $this->product();
        // 2026-11-09 18:00 UTC = 10 Nov 01:00 WIB -> masuk 10 Nov
        $this->order($p, 1, 'selesai', 'same', 0, '2026-11-09 18:00:00');

        $this->assertSame(1, app(ProfitLossService::class)->report('2026-11-10', '2026-11-10')['order_count']);
        $this->assertSame(0, app(ProfitLossService::class)->report('2026-11-09', '2026-11-09')['order_count']);
    }

    public function test_profit_page_validates_dates_and_swaps_reversed_range(): void
    {
        $k = $this->userAs('keuangan');

        $this->actingAs($k)->get(route('admin.finance.profit', ['from' => 'bukan-tanggal']))->assertSessionHasErrors('from');
        $this->actingAs($k)->get(route('admin.finance.profit', ['from' => '2026-11-30', 'to' => '2026-11-01']))->assertOk()->assertSee('2026-11-01 s/d 2026-11-30');
    }

    public function test_csv_export_has_totals_and_guards_against_formula_injection(): void
    {
        $p = $this->product();
        $p->update(['name' => '=HYPERLINK("http://x")']);
        $this->order($p, 1);

        $res = $this->actingAs($this->userAs('keuangan'))->get(route('admin.finance.profit.export', ['from' => '2026-11-01', 'to' => '2026-11-30']));
        $res->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringContainsString('Laba bersih', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(";=HYPERLINK", $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    // ================= biaya operasional =================

    public function test_expense_crud_with_audit_log(): void
    {
        $k = $this->userAs('keuangan');

        $this->actingAs($k)->post(route('admin.expenses.store'), [
            'expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'Sewa ruko', 'amount' => 3500000,
        ])->assertRedirect(route('admin.expenses.index'));

        $e = Expense::first();
        $this->assertSame($k->id, $e->created_by);
        $this->assertEquals(3500000, $e->amount);

        $this->actingAs($k)->put(route('admin.expenses.update', $e->id), [
            'expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'Sewa ruko Nov', 'amount' => 4000000,
        ])->assertRedirect();
        $this->assertSame('Sewa ruko Nov', $e->fresh()->description);

        $this->actingAs($k)->get(route('admin.expenses.index'))->assertOk()->assertSee('Sewa ruko Nov');

        $this->actingAs($k)->delete(route('admin.expenses.destroy', $e->id))->assertRedirect();
        $this->assertSoftDeleted('expenses', ['id' => $e->id]);

        $this->assertGreaterThanOrEqual(3, Activity::where('log_name', 'keuangan')->count());
    }

    public function test_expense_validation(): void
    {
        $k = $this->userAs('keuangan');
        $base = ['expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'X', 'amount' => 1000];

        foreach ([
            ['amount' => 0], ['amount' => -5], ['amount' => 'abc'], ['category' => 'ngawur'], ['description' => ''],
            ['expense_date' => '05-11-2026'], ['expense_date' => now('Asia/Jakarta')->addDays(3)->toDateString()],
        ] as $bad) {
            $this->actingAs($k)->post(route('admin.expenses.store'), array_merge($base, $bad))->assertSessionHasErrors();
        }
        $this->assertSame(0, Expense::count());
    }

    public function test_staff_cannot_touch_expenses(): void
    {
        $staff = $this->userAs('staff');
        $e = $this->expense('2026-11-05', 1000);

        $this->actingAs($staff)->post(route('admin.expenses.store'), ['expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'X', 'amount' => 5])->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.expenses.destroy', $e->id))->assertForbidden();
        $this->assertNull($e->fresh()->deleted_at);
    }

    // ================= HPP =================

    public function test_update_costs_and_apply_to_past_orders(): void
    {
        $p = $this->product(cost: null);
        $o = $this->order($p, 2, 'selesai', null);

        // tanpa centang: pesanan lama tetap kosong
        $this->actingAs($this->userAs('keuangan'))->put(route('admin.finance.costs.update'), ['costs' => [$p->id => '700000']])->assertRedirect();
        $this->assertEquals(700000, $p->fresh()->cost_price);
        $this->assertNull($o->items()->first()->cost_price);

        // dengan centang: pesanan lama yang kosong terisi
        $this->actingAs($this->userAs('keuangan'))->put(route('admin.finance.costs.update'), ['costs' => [$p->id => '650000'], 'apply_to_past' => '1'])->assertRedirect();
        $this->assertEquals(650000, $p->fresh()->cost_price);
        $this->assertEquals(650000, $o->items()->first()->cost_price);
    }

    public function test_apply_to_past_never_overwrites_existing_snapshot(): void
    {
        $p = $this->product(cost: 500000);
        $o = $this->order($p, 1, 'selesai', 500000);

        $this->actingAs($this->userAs('keuangan'))->put(route('admin.finance.costs.update'), ['costs' => [$p->id => '999000'], 'apply_to_past' => '1']);

        $this->assertEquals(999000, $p->fresh()->cost_price);
        $this->assertEquals(500000, $o->items()->first()->cost_price); // snapshot lama utuh
    }

    public function test_cost_validation_and_permission(): void
    {
        $p = $this->product();
        $k = $this->userAs('keuangan');

        $this->actingAs($k)->put(route('admin.finance.costs.update'), ['costs' => [$p->id => '-1']])->assertSessionHasErrors('costs.'.$p->id);
        $this->actingAs($k)->put(route('admin.finance.costs.update'), ['costs' => [$p->id => 'abc']])->assertSessionHasErrors('costs.'.$p->id);
        $this->assertEquals(600000, $p->fresh()->cost_price);

        $this->actingAs($this->userAs('staff'))->put(route('admin.finance.costs.update'), ['costs' => [$p->id => '1']])->assertForbidden();
        $this->assertEquals(600000, $p->fresh()->cost_price);
    }

    public function test_cost_change_is_audited_and_blank_clears_cost(): void
    {
        $p = $this->product(cost: 600000);

        $this->actingAs($this->userAs('keuangan'))->put(route('admin.finance.costs.update'), ['costs' => [$p->id => '']]);

        $this->assertNull($p->fresh()->cost_price);
        $this->assertTrue(Activity::where('log_name', 'produk')->where('subject_id', $p->id)->get()->contains(fn ($a) => array_key_exists('cost_price', (array) ($a->attribute_changes['attributes'] ?? []))));
    }

    public function test_costs_page_filter_shows_only_empty(): void
    {
        $with = $this->product(cost: 1000);
        $without = $this->product(cost: null);

        $this->actingAs($this->userAs('keuangan'))->get(route('admin.finance.costs', ['filter' => 'kosong']))
            ->assertOk()->assertSee($without->name)->assertDontSee($with->name);
    }

    // ================= salin HPP saat checkout =================

    public function test_checkout_snapshots_cost_price_into_order_items(): void
    {
        $prov = \App\Models\Province::create(['code' => '32', 'name' => 'Jawa Barat']);
        $city = \App\Models\City::create(['province_id' => $prov->id, 'code' => '32.73', 'name' => 'Kota Bandung']);
        $user = User::factory()->create();
        $withCost = $this->product(cost: 640000, price: 1000000);
        $noCost = $this->product(cost: null, price: 500000);

        $line = fn (Product $p, int $qty) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => $qty, 'weight' => 1000, 'image' => null, 'stock' => $p->stock];

        $this->actingAs($user)
            ->withSession(['cart' => [$withCost->id => $line($withCost, 2), $noCost->id => $line($noCost, 1)], 'checkout_key' => 'k1'])
            ->post(route('customer.checkout.store'), [
                'idempotency_key' => 'k1', 'name' => 'Budi', 'phone' => '0812', 'address' => 'Jl. Kopi 1',
                'province_id' => $prov->id, 'city_id' => $city->id, 'postal_code' => '40111',
            ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertEquals(640000, $order->items->firstWhere('product_id', $withCost->id)->cost_price);
        $this->assertNull($order->items->firstWhere('product_id', $noCost->id)->cost_price);

        // HPP produk berubah belakangan: salinan di pesanan tidak ikut berubah
        $withCost->update(['cost_price' => 900000]);
        $this->assertEquals(640000, $order->items()->where('product_id', $withCost->id)->first()->cost_price);
    }
}
