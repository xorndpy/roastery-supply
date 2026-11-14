<?php

namespace Tests\Feature\Finance;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinanceClosing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\FinanceClosingService;
use App\Services\ProfitLossService;
use App\Support\GatewayFee;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Finance v2: biaya gateway, PPN, jasa servis, tutup buku, lampiran, biaya berulang, tren. */
class FinanceV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::forgetMemo();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userAs(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function order(float $price = 1000000, int $qty = 1, float $cost = 600000, string $at = '2026-11-10 05:00:00', string $status = 'selesai'): Order
    {
        static $n = 0;
        $n++;
        $p = Product::unguarded(fn () => Product::create(['name' => "Mesin G{$n}", 'slug' => "mesin-g{$n}", 'sku' => "G-{$n}", 'price' => $price, 'cost_price' => $cost, 'stock' => 50, 'stock_threshold' => 1, 'is_active' => true]));
        $u = User::factory()->create();
        $c = Customer::unguarded(fn () => Customer::create(['user_id' => $u->id, 'name' => $u->name, 'email' => $u->email]));
        $o = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $c->id, 'user_id' => $u->id, 'status' => $status, 'subtotal' => $price * $qty,
            'discount' => 0, 'shipping_cost' => 0, 'total' => $price * $qty, 'shipping_name' => 'Budi', 'created_at' => $at, 'updated_at' => $at,
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => $price, 'cost_price' => $cost, 'quantity' => $qty, 'subtotal' => $price * $qty]));

        return $o;
    }

    private function gatewayPayment(Order $o, string $type, float $amount, ?float $fee = null, string $paidAt = '2026-11-10 06:00:00'): Payment
    {
        return Payment::unguarded(fn () => Payment::create([
            'order_id' => $o->id, 'method' => 'gateway', 'provider' => 'midtrans', 'payment_type' => $type, 'amount' => $amount,
            'status' => 'verified', 'paid_at' => $paidAt, 'verified_at' => $paidAt, 'gateway_fee' => $fee,
        ]));
    }

    // ---------- biaya payment gateway ----------

    public function test_gateway_fee_rules(): void
    {
        $this->assertSame(4000.0, GatewayFee::for('bank_transfer', 5000000));
        $this->assertSame(35000.0, GatewayFee::for('qris', 5000000));                  // 0,7%
        $this->assertSame(2000 + 2900.0, GatewayFee::for('credit_card', 100000));      // 2.000 + 2,9%
        $this->assertSame(0.0, GatewayFee::for('tipe_baru_tak_dikenal', 1000000));
        $this->assertSame(0.0, GatewayFee::for(null, 1000000));
    }

    public function test_fee_is_snapshotted_when_a_gateway_payment_becomes_verified(): void
    {
        $o = $this->order();
        $p = Payment::unguarded(fn () => Payment::create(['order_id' => $o->id, 'method' => 'gateway', 'provider' => 'midtrans', 'payment_type' => 'qris', 'amount' => 1000000, 'status' => 'pending']));
        $this->assertNull($p->fresh()->gateway_fee);

        $p->update(['status' => 'verified']);
        $this->assertEquals(7000, $p->fresh()->gateway_fee);

        // tarif berubah belakangan: snapshot lama tidak ikut berubah
        config(['finance.gateway_fees.qris' => ['fixed' => 0, 'percent' => 5]]);
        $p->update(['note' => 'x']);
        $this->assertEquals(7000, $p->fresh()->gateway_fee);
    }

    public function test_manual_transfer_has_no_gateway_fee(): void
    {
        $o = $this->order();
        $p = Payment::unguarded(fn () => Payment::create(['order_id' => $o->id, 'method' => 'manual_transfer', 'amount' => 1000000, 'status' => 'verified']));

        $this->assertNull($p->fresh()->gateway_fee);
        $this->assertEquals(0, app(ProfitLossService::class)->report('2026-11-01', '2026-11-30')['gateway_fees']);
    }

    public function test_profit_loss_deducts_gateway_fees_using_snapshot_or_rule_fallback(): void
    {
        $o1 = $this->order();
        $this->gatewayPayment($o1, 'bank_transfer', 1000000, 4000);               // snapshot
        $o2 = $this->order();
        $this->gatewayPayment($o2, 'qris', 2000000, null);                          // tanpa snapshot -> aturan 0,7% = 14.000
        $o3 = $this->order();
        $this->gatewayPayment($o3, 'qris', 9000000, 63000, '2026-10-01 06:00:00'); // di luar periode

        $r = app(ProfitLossService::class)->report('2026-11-01', '2026-11-30');

        $this->assertEquals(18000, $r['gateway_fees']);
        $this->assertSame(2, $r['gateway_count']);
        // 3 pesanan November: pendapatan 3.000.000, HPP 1.800.000; gateway hanya yang dibayar di November (18.000)
        $this->assertEquals(1200000 - 18000, $r['net_profit']);
    }

    // ---------- PPN ----------

    public function test_vat_is_off_by_default_and_splits_included_vat_when_enabled(): void
    {
        $this->order(1110000, 1, 500000);
        $svc = app(ProfitLossService::class);

        $off = $svc->report('2026-11-01', '2026-11-30');
        $this->assertNull($off['tax_rate']);
        $this->assertEquals(1110000, $off['revenue']);

        Setting::set('tax_enabled', '1');
        Setting::set('tax_rate', '11');
        $on = $svc->report('2026-11-01', '2026-11-30');

        $this->assertEquals(110000, $on['ppn']);                  // 1.110.000 x 11/111
        $this->assertEquals(1000000, $on['revenue']);
        $this->assertEquals(500000, $on['gross_profit']);
    }

    public function test_vat_shows_on_the_profit_page_and_the_invoice(): void
    {
        Setting::set('tax_enabled', '1');
        Setting::set('tax_rate', '11');
        $o = $this->order(1110000);

        $this->actingAs($this->userAs('keuangan'))->get(route('admin.finance.profit', ['from' => '2026-11-01', 'to' => '2026-11-30']))
            ->assertOk()->assertSee('PPN keluaran 11%');

        $o->load(['customer', 'items', 'payment']);
        $this->assertStringContainsString('Sudah termasuk PPN 11%', view('pdf.invoice', ['order' => $o])->render());

        Setting::set('tax_enabled', '0');
        $this->assertStringNotContainsString('Sudah termasuk PPN', view('pdf.invoice', ['order' => $o])->render());
    }

    // ---------- jasa servis ----------

    public function test_paid_service_revenue_is_included_but_warranty_and_unpaid_are_not(): void
    {
        $u = User::factory()->create();
        $ticket = fn (array $x) => DB::table('service_tickets')->insert($x + [
            'ticket_number' => 'SRV-'.uniqid(), 'user_id' => $u->id, 'product_name' => 'Mesin', 'issue_type' => 'rusak', 'description' => 'x',
            'contact_phone' => '0812', 'status' => 'selesai', 'in_warranty' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ticket(['final_cost' => 750000, 'paid_at' => '2026-11-10 05:00:00']);                          // dihitung
        $ticket(['final_cost' => 0, 'paid_at' => '2026-11-10 05:00:00', 'in_warranty' => true]);         // garansi
        $ticket(['final_cost' => 500000, 'paid_at' => null]);                                          // belum dibayar
        $ticket(['final_cost' => 900000, 'paid_at' => '2026-10-02 05:00:00']);                         // luar periode

        $r = app(ProfitLossService::class)->report('2026-11-01', '2026-11-30');

        $this->assertEquals(750000, $r['service_revenue']);
        $this->assertEquals(750000, $r['revenue']);
    }

    // ---------- tutup buku ----------

    public function test_closing_locks_expenses_and_stores_a_snapshot(): void
    {
        Expense::create(['expense_date' => '2026-10-05', 'category' => 'sewa', 'description' => 'Sewa Okt', 'amount' => 2000000]);
        $this->order(1000000, 1, 600000, '2026-10-10 05:00:00');
        $keu = $this->userAs('keuangan');

        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'note' => 'Okt'])->assertSessionHasNoErrors();

        $c = FinanceClosing::firstOrFail();
        $this->assertEquals(1000000, $c->snapshot['revenue']);
        $this->assertEquals(-1600000, $c->snapshot['net_profit']);
        $this->assertArrayNotHasKey('products', $c->snapshot);
        $this->assertSame('2026-10-31', app(FinanceClosingService::class)->lockedUntil());

        // biaya di periode terkunci tidak bisa dibuat/diubah/dihapus
        $this->actingAs($keu)->post(route('admin.expenses.store'), ['expense_date' => '2026-10-20', 'category' => 'sewa', 'description' => 'X', 'amount' => 1000])->assertSessionHasErrors('expense_date');
        $locked = Expense::first();
        $this->actingAs($keu)->put(route('admin.expenses.update', $locked->id), ['expense_date' => '2026-11-02', 'category' => 'sewa', 'description' => 'Pindah', 'amount' => 5])->assertSessionHasErrors('expense_date');
        $this->actingAs($keu)->delete(route('admin.expenses.destroy', $locked->id))->assertSessionHas('error');
        $this->assertSame('Sewa Okt', $locked->fresh()->description);

        // periode berikutnya tetap bebas
        $this->actingAs($keu)->post(route('admin.expenses.store'), ['expense_date' => '2026-11-02', 'category' => 'sewa', 'description' => 'Sewa Nov', 'amount' => 1000])->assertSessionHasNoErrors();
    }

    public function test_closing_rules(): void
    {
        $keu = $this->userAs('keuangan');
        $svc = app(FinanceClosingService::class);
        $this->order(1000000, 1, 600000, '2026-09-15 05:00:00');
        $today = now('Asia/Jakarta')->toDateString();

        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => $svc->suggestedStart(), 'period_end' => $today])->assertSessionHasErrors('period_end');   // hari ini belum selesai
        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertSessionHasNoErrors();
        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertSessionHasErrors('period_end'); // tumpang tindih
        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-10-05', 'period_end' => '2026-10-31'])->assertSessionHasErrors('period_end'); // ada celah
        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-10-01', 'period_end' => '2026-09-20'])->assertSessionHasErrors('period_end');
        $this->assertSame(1, FinanceClosing::count());
    }

    public function test_only_the_latest_closing_can_be_reopened_and_needs_a_reason(): void
    {
        $keu = $this->userAs('keuangan');
        $this->order(1000000, 1, 600000, '2026-09-15 05:00:00');
        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30']);
        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-10-01', 'period_end' => '2026-10-31']);
        [$sep, $oct] = [FinanceClosing::orderBy('period_end')->first(), FinanceClosing::orderByDesc('period_end')->first()];

        $this->actingAs($keu)->delete(route('admin.finance.closings.reopen', $sep->id), ['reason' => 'ingin mengubah'])->assertSessionHasErrors('reason'); // bukan yang terakhir
        $this->actingAs($keu)->delete(route('admin.finance.closings.reopen', $oct->id), ['reason' => 'abc'])->assertSessionHasErrors('reason');         // terlalu pendek
        $this->actingAs($keu)->delete(route('admin.finance.closings.reopen', $oct->id), ['reason' => 'Ada nota yang terlewat'])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-30', app(FinanceClosingService::class)->lockedUntil());
        $this->assertSame('Ada nota yang terlewat', FinanceClosing::withTrashed()->find($oct->id)->reopen_reason);
    }

    public function test_drift_is_reported_when_data_changes_after_closing(): void
    {
        $o = $this->order(1000000, 1, 600000, '2026-09-15 05:00:00');
        $keu = $this->userAs('keuangan');
        $this->actingAs($keu)->post(route('admin.finance.closings.store'), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30']);
        $c = FinanceClosing::firstOrFail();

        $this->assertEquals(0, app(FinanceClosingService::class)->drift($c)['net_profit']);

        ReturnOrder::unguarded(fn () => ReturnOrder::create([
            'order_id' => $o->id, 'reason' => 'Rusak', 'reason_type' => 'rusak', 'evidence' => ['r/x.jpg'], 'status' => 'approved', 'refund_amount' => 300000,
            'approved_at' => '2026-09-20 03:00:00', 'refund_bank' => 'BCA', 'refund_account_number' => '1', 'refund_account_name' => 'B',
        ]));

        $this->assertEquals(-300000, app(FinanceClosingService::class)->drift($c)['net_profit']);
        $this->actingAs($keu)->get(route('admin.finance.closings'))->assertOk()->assertSee('Laba -Rp 300.000');
    }

    public function test_staff_cannot_close_the_books_but_admin_can(): void
    {
        $this->order(1000000, 1, 600000, '2026-09-15 05:00:00');
        $this->actingAs($this->userAs('staff'))->post(route('admin.finance.closings.store'), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertForbidden();
        $this->actingAs($this->userAs('admin'))->post(route('admin.finance.closings.store'), ['period_start' => '2026-09-01', 'period_end' => '2026-09-30'])->assertSessionHasNoErrors();
        $this->assertSame(1, FinanceClosing::count());
    }

    // ---------- lampiran ----------

    public function test_expense_attachment_is_private_downloadable_and_validated(): void
    {
        Storage::fake('local');
        $keu = $this->userAs('keuangan');

        $this->actingAs($keu)->post(route('admin.expenses.store'), [
            'expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'Sewa', 'amount' => 1000,
            'attachment' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $e = Expense::firstOrFail();
        Storage::disk('local')->assertExists($e->attachment);
        $this->actingAs($keu)->get(route('admin.expenses.attachment', $e->id))->assertOk();

        $this->actingAs($this->userAs('gudang'))->get(route('admin.expenses.attachment', $e->id))->assertForbidden();
        $this->actingAs($this->userAs('customer'))->get(route('admin.expenses.attachment', $e->id))->assertForbidden();

        foreach ([UploadedFile::fake()->create('virus.exe', 10), UploadedFile::fake()->create('besar.pdf', 4000, 'application/pdf')] as $bad) {
            $this->actingAs($keu)->post(route('admin.expenses.store'), ['expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'X', 'amount' => 1, 'attachment' => $bad])->assertSessionHasErrors('attachment');
        }
        $this->assertSame(1, Expense::count());
    }

    public function test_replacing_and_removing_an_attachment_cleans_the_old_file(): void
    {
        Storage::fake('local');
        $keu = $this->userAs('keuangan');
        $this->actingAs($keu)->post(route('admin.expenses.store'), ['expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'Sewa', 'amount' => 1000, 'attachment' => UploadedFile::fake()->image('a.jpg')]);
        $e = Expense::firstOrFail();
        $old = $e->attachment;

        $this->actingAs($keu)->put(route('admin.expenses.update', $e->id), ['expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'Sewa', 'amount' => 1000, 'attachment' => UploadedFile::fake()->image('b.jpg')]);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($e->fresh()->attachment);

        $this->actingAs($keu)->put(route('admin.expenses.update', $e->id), ['expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'Sewa', 'amount' => 1000, 'remove_attachment' => '1']);
        $this->assertNull($e->fresh()->attachment);
    }

    // ---------- biaya berulang ----------

    public function test_recurring_expenses_are_copied_once_per_month(): void
    {
        $now = now('Asia/Jakarta');
        $prev = $now->copy()->subMonthNoOverflow()->startOfMonth();
        Expense::create(['expense_date' => $prev->copy()->day(1)->toDateString(), 'category' => 'sewa', 'description' => 'Sewa ruko', 'amount' => 3000000, 'is_recurring' => true]);
        Expense::create(['expense_date' => $prev->copy()->day(2)->toDateString(), 'category' => 'lainnya', 'description' => 'Sekali saja', 'amount' => 100000, 'is_recurring' => false]);
        $keu = $this->userAs('keuangan');

        $this->actingAs($keu)->post(route('admin.expenses.copy-recurring'))->assertSessionHas('success');
        $this->assertSame(1, Expense::where('description', 'Sewa ruko')->whereBetween('expense_date', [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()])->count());
        $this->assertSame(0, Expense::where('description', 'Sekali saja')->whereBetween('expense_date', [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()])->count());

        $this->actingAs($keu)->post(route('admin.expenses.copy-recurring'))->assertSessionHas('error'); // tidak ada duplikat
        $this->assertSame(2, Expense::where('description', 'Sewa ruko')->count());
    }

    // ---------- tren & dashboard ----------

    public function test_trend_returns_six_months_with_figures(): void
    {
        $this->order(1000000, 1, 600000, '2026-09-10 05:00:00');
        $this->order(2000000, 1, 600000, '2026-11-05 05:00:00');

        $trend = app(ProfitLossService::class)->trend('2026-11-12', 6);

        $this->assertCount(6, $trend);
        $byLabel = collect($trend)->keyBy('label');
        $this->assertEquals(1000000, $byLabel->first(fn ($t) => $t['from'] === '2026-09-01')['revenue']);
        $this->assertEquals(2000000, $byLabel->first(fn ($t) => $t['from'] === '2026-11-01')['revenue']);
        $this->actingAs($this->userAs('keuangan'))->get(route('admin.finance.profit', ['from' => '2026-11-01', 'to' => '2026-11-12']))->assertOk()->assertSee('Tren 6 bulan');
    }
}
