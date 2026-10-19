<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\ReturnStatusChanged;
use App\Services\OrderService;
use App\Services\ReturnService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    // ---------- helpers ----------

    private function userAs(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function product(int $stock = 5): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Mesin R{$n}", 'slug' => "mesin-r{$n}", 'sku' => "R-{$n}", 'price' => 4000000, 'stock' => $stock, 'stock_threshold' => 1, 'is_active' => true]));
    }

    /** Order selesai $daysAgo hari lalu, berisi 2 unit dari $product. */
    private function completedOrder(?User $user = null, int $daysAgo = 2, ?Product $product = null, string $status = 'selesai'): Order
    {
        $user ??= User::factory()->create();
        $product ??= $this->product();
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]));

        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => $status,
            'subtotal' => 8000000, 'total' => 8000000, 'completed_at' => $status === 'selesai' ? now()->subDays($daysAgo) : null,
            'shipping_name' => 'Budi',
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'product_sku' => $product->sku, 'price' => 4000000, 'quantity' => 2, 'subtotal' => 8000000]));

        return $order;
    }

    private function formData(Order $order, array $over = []): array
    {
        return array_merge([
            'order_number' => $order->order_number, 'reason_type' => 'rusak', 'reason' => 'Mesin penyok di bagian samping saat dibuka.',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            'refund_bank' => 'BCA', 'refund_account_number' => '1234567890', 'refund_account_name' => 'Budi Santoso',
        ], $over);
    }

    private function submitAs(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('customer.returns.store'), $data);
    }

    private function makeReturn(?Order $order = null, string $status = 'pending'): ReturnOrder
    {
        $order ??= $this->completedOrder();

        return ReturnOrder::create([
            'order_id' => $order->id, 'reason' => 'Rusak parah', 'reason_type' => 'rusak', 'status' => $status, 'refund_amount' => $order->total,
            'evidence' => ['returns/x.jpg'], 'refund_bank' => 'BCA', 'refund_account_number' => '123456', 'refund_account_name' => 'Budi',
        ]);
    }

    // ---------- kelayakan ----------

    public function test_eligibility_rules(): void
    {
        $svc = app(ReturnService::class);

        $this->assertNull($svc->ineligibleReason($this->completedOrder(null, 2)));
        $this->assertNull($svc->ineligibleReason($this->completedOrder(null, 6)));   // masih dalam 7 hari
        $this->assertStringContainsString('7 hari', $svc->ineligibleReason($this->completedOrder(null, 8)));

        $this->assertStringContainsString('sudah selesai', $svc->ineligibleReason($this->completedOrder(null, 1, null, 'dikirim')));

        $noDate = $this->completedOrder();
        $noDate->forceFill(['completed_at' => null])->save();
        $this->assertStringContainsString('tidak tercatat', $svc->ineligibleReason($noDate));

        $has = $this->completedOrder();
        $this->makeReturn($has);
        $this->assertStringContainsString('sudah pernah', $svc->ineligibleReason($has->fresh()));
    }

    public function test_completing_an_order_records_the_time_that_starts_the_7_day_window(): void
    {
        $order = $this->completedOrder(null, 0, null, 'dikirim');

        app(OrderService::class)->complete($order);

        $order->refresh();
        $this->assertNotNull($order->completed_at);
        $this->assertSame(7, (int) round($order->completed_at->diffInDays($order->return_deadline)));
    }

    // ---------- pengajuan customer ----------

    public function test_customer_submits_a_return_with_private_evidence(): void
    {
        $user = User::factory()->create();
        $order = $this->completedOrder($user);

        $this->submitAs($user, $this->formData($order))->assertSessionHasNoErrors();

        $r = ReturnOrder::first();
        $this->assertSame('pending', $r->status);
        $this->assertSame($order->id, $r->order_id);
        $this->assertSame('rusak', $r->reason_type);
        $this->assertSame('8000000.00', (string) $r->refund_amount);   // default = total pesanan
        $this->assertCount(2, $r->evidence);
        foreach ($r->evidence as $path) {
            Storage::disk('local')->assertExists($path);
            Storage::disk('public')->assertMissing($path);             // tidak pernah di disk publik
        }
        $this->assertSame('1234567890', $r->refund_account_number);
    }

    public function test_return_form_validation(): void
    {
        $user = User::factory()->create();
        $order = $this->completedOrder($user);
        $img = fn () => UploadedFile::fake()->image('x.jpg');

        $this->submitAs($user, $this->formData($order, ['photos' => []]))->assertSessionHasErrors('photos');
        $this->submitAs($user, $this->formData($order, ['photos' => [$img(), $img(), $img(), $img()]]))->assertSessionHasErrors('photos');
        $this->submitAs($user, $this->formData($order, ['photos' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]]))->assertSessionHasErrors('photos.0');
        $this->submitAs($user, $this->formData($order, ['reason' => 'rusak']))->assertSessionHasErrors('reason');
        $this->submitAs($user, $this->formData($order, ['reason_type' => 'iseng']))->assertSessionHasErrors('reason_type');
        $this->submitAs($user, $this->formData($order, ['refund_account_number' => '12-abc']))->assertSessionHasErrors('refund_account_number');
        $this->submitAs($user, $this->formData($order, ['refund_bank' => '']))->assertSessionHasErrors('refund_bank');

        $this->assertSame(0, ReturnOrder::count());
    }

    public function test_cannot_return_after_7_days_or_before_completion(): void
    {
        $user = User::factory()->create();

        $late = $this->completedOrder($user, 8);
        $this->submitAs($user, $this->formData($late))->assertSessionHas('error');

        $shipped = $this->completedOrder($user, 1, null, 'dikirim');
        $this->submitAs($user, $this->formData($shipped))->assertSessionHas('error');

        $this->assertSame(0, ReturnOrder::count());
        $this->assertSame([], Storage::disk('local')->allFiles('returns')); // ditolak sebelum foto disimpan
    }

    public function test_only_one_return_per_order_and_failed_submit_leaves_no_files(): void
    {
        $user = User::factory()->create();
        $order = $this->completedOrder($user);

        $this->submitAs($user, $this->formData($order))->assertSessionHasNoErrors();
        $filesAfterFirst = Storage::disk('local')->allFiles('returns');

        $this->submitAs($user, $this->formData($order))->assertSessionHas('error');

        $this->assertSame(1, ReturnOrder::count());
        $this->assertSame($filesAfterFirst, Storage::disk('local')->allFiles('returns'));
    }

    public function test_cannot_return_someone_elses_order(): void
    {
        $order = $this->completedOrder();
        $stranger = User::factory()->create();

        $this->submitAs($stranger, $this->formData($order))->assertNotFound();
        $this->actingAs($stranger)->get(route('customer.returns.create', $order->order_number))->assertNotFound();
        $this->assertSame(0, ReturnOrder::count());
    }

    public function test_return_pages_render_for_the_customer(): void
    {
        $user = User::factory()->create();
        $order = $this->completedOrder($user);

        $this->actingAs($user)->get(route('customer.returns.index'))->assertOk()->assertSee('Belum ada pengajuan');
        $this->actingAs($user)->get(route('customer.returns.create'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($user)->get(route('customer.returns.create', $order->order_number))->assertOk()->assertSee('Rekening untuk refund');

        $this->submitAs($user, $this->formData($order));
        $r = ReturnOrder::first();

        $this->actingAs($user)->get(route('customer.returns.index'))->assertOk()->assertSee($order->order_number)->assertSee('Menunggu tinjauan');
        $this->actingAs($user)->get(route('customer.returns.show', $r->id))->assertOk()->assertSee('Barang rusak saat diterima')->assertSee('1234567890');

        // sudah diajukan -> halaman form mengarahkan balik dengan pesan
        $this->actingAs($user)->get(route('customer.returns.create', $order->order_number))->assertRedirect(route('customer.orders.show', $order->order_number));
    }

    public function test_order_page_offers_return_only_when_eligible(): void
    {
        $user = User::factory()->create();
        $ok = $this->completedOrder($user, 2);
        $late = $this->completedOrder($user, 9);

        $this->actingAs($user)->get(route('customer.orders.show', $ok->order_number))->assertOk()->assertSee('Ajukan Retur')->assertSee('Ada masalah dengan pesanan?');
        $this->actingAs($user)->get(route('customer.orders.show', $late->order_number))->assertOk()->assertDontSee('Ajukan Retur');

        $this->submitAs($user, $this->formData($ok));
        $this->actingAs($user)->get(route('customer.orders.show', $ok->order_number))->assertOk()->assertDontSee('Ajukan Retur')->assertSee('sedang ditinjau');
    }

    public function test_customer_cannot_see_other_customers_returns(): void
    {
        $r = $this->makeReturn();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('customer.returns.show', $r->id))->assertNotFound();
        $this->actingAs($stranger)->get(route('customer.returns.evidence', [$r->id, 0]))->assertNotFound();
        $this->actingAs($stranger)->get(route('customer.returns.index'))->assertOk()->assertDontSee($r->order->order_number);
    }

    // ---------- foto bukti privat ----------

    public function test_evidence_is_served_to_the_owner_and_staff_only(): void
    {
        $user = User::factory()->create();
        $order = $this->completedOrder($user);
        $this->submitAs($user, $this->formData($order));
        $r = ReturnOrder::first();

        $this->actingAs($user)->get(route('customer.returns.evidence', [$r->id, 0]))->assertOk();
        $this->actingAs($this->userAs('staff'))->get(route('admin.returns.evidence', [$r->id, 1]))->assertOk();   // staff: view_returns
        $this->actingAs($this->userAs('customer'))->get(route('admin.returns.evidence', [$r->id, 0]))->assertForbidden();
        $this->actingAs($user)->get(route('customer.returns.evidence', [$r->id, 9]))->assertNotFound();           // indeks tak ada

        auth()->logout();
        $this->get(route('customer.returns.evidence', [$r->id, 0]))->assertRedirect(route('login'));
    }

    // ---------- keputusan admin ----------

    public function test_admin_approves_with_default_refund_and_notifies_the_customer(): void
    {
        $admin = $this->userAs('admin');
        $r = $this->makeReturn();

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id), ['note' => 'Silakan tunggu transfer.'])->assertSessionHas('success');

        $r->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertSame('8000000.00', (string) $r->refund_amount);
        $this->assertSame($admin->id, $r->approved_by);
        $this->assertNotNull($r->approved_at);
        $this->assertNull($r->refunded_at);
        Notification::assertSentTo($r->order->user, ReturnStatusChanged::class, fn ($n) => $n->event === 'approved');
    }

    public function test_refund_cannot_exceed_the_order_total(): void
    {
        $admin = $this->userAs('admin');
        $r = $this->makeReturn();

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id), ['refund_amount' => 8000001])->assertSessionHas('error');
        $this->assertSame('pending', $r->fresh()->status);

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id), ['refund_amount' => 6500000])->assertSessionHas('success');   // refund sebagian boleh
        $this->assertSame('6500000.00', (string) $r->fresh()->refund_amount);
    }

    public function test_restock_adds_stock_back_and_records_a_movement_only_when_chosen(): void
    {
        $admin = $this->userAs('admin');
        $p = $this->product(5);
        $r = $this->makeReturn($this->completedOrder(null, 1, $p));

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id), ['restock' => 1])->assertSessionHas('success');

        $this->assertSame(7, $p->fresh()->stock);   // 5 + 2 unit
        $this->assertTrue($r->fresh()->restocked);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'in', 'quantity' => 2, 'reference_type' => 'return', 'reference_id' => $r->id, 'user_id' => $admin->id]);

        $p2 = $this->product(5);
        $r2 = $this->makeReturn($this->completedOrder(null, 1, $p2));
        $this->actingAs($admin)->post(route('admin.returns.approve', $r2->id));
        $this->assertSame(5, $p2->fresh()->stock);   // barang rusak: stok tidak bertambah
        $this->assertFalse($r2->fresh()->restocked);
        $this->assertSame(0, StockMovement::where('product_id', $p2->id)->count());
    }

    public function test_reject_requires_a_reason_and_is_final(): void
    {
        $admin = $this->userAs('admin');
        $r = $this->makeReturn();

        $this->actingAs($admin)->post(route('admin.returns.reject', $r->id), ['note' => ''])->assertSessionHasErrors('note');
        $this->assertSame('pending', $r->fresh()->status);

        $this->actingAs($admin)->post(route('admin.returns.reject', $r->id), ['note' => 'Kerusakan akibat pemakaian.'])->assertSessionHas('success');
        $r->refresh();
        $this->assertSame('rejected', $r->status);
        $this->assertSame('Kerusakan akibat pemakaian.', $r->note);
        $this->assertSame('0.00', (string) $r->refund_amount);
        Notification::assertSentTo($r->order->user, ReturnStatusChanged::class, fn ($n) => $n->event === 'rejected');

        // sudah diproses: tidak bisa disetujui / ditolak lagi
        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.returns.reject', $r->id), ['note' => 'lagi'])->assertSessionHas('error');
        $this->assertSame('rejected', $r->fresh()->status);
    }

    public function test_approving_twice_does_not_restock_twice(): void
    {
        $admin = $this->userAs('admin');
        $p = $this->product(5);
        $r = $this->makeReturn($this->completedOrder(null, 1, $p));

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id), ['restock' => 1]);
        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id), ['restock' => 1])->assertSessionHas('error');

        $this->assertSame(7, $p->fresh()->stock);
        $this->assertSame(1, StockMovement::where('reference_type', 'return')->count());
    }

    public function test_mark_refunded_flow(): void
    {
        $admin = $this->userAs('admin');
        $r = $this->makeReturn();

        $this->actingAs($admin)->post(route('admin.returns.refunded', $r->id))->assertSessionHas('error');   // belum disetujui

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id));
        $this->actingAs($admin)->post(route('admin.returns.refunded', $r->id))->assertSessionHas('success');
        $this->assertNotNull($r->fresh()->refunded_at);
        $this->assertSame('refunded', $r->fresh()->stage);
        Notification::assertSentTo($r->order->user, ReturnStatusChanged::class, fn ($n) => $n->event === 'refunded');

        $this->actingAs($admin)->post(route('admin.returns.refunded', $r->id))->assertSessionHas('error');   // tidak dobel
    }

    public function test_staff_can_view_returns_but_not_decide(): void
    {
        $staff = $this->userAs('staff');
        $r = $this->makeReturn();

        $this->actingAs($staff)->get(route('admin.returns.index'))->assertOk()->assertSee($r->order->order_number);
        $this->actingAs($staff)->get(route('admin.returns.show', $r->id))->assertOk()->assertDontSee('Setujui Retur');
        $this->actingAs($staff)->post(route('admin.returns.approve', $r->id))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.returns.reject', $r->id), ['note' => 'x'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.returns.refunded', $r->id))->assertForbidden();
        $this->assertSame('pending', $r->fresh()->status);
    }

    public function test_admin_return_pages_render_in_every_state(): void
    {
        $admin = $this->userAs('admin');
        $pending = $this->makeReturn();
        $approved = $this->makeReturn(null, 'approved');
        $approved->forceFill(['approved_by' => $admin->id, 'approved_at' => now(), 'restocked' => true])->save();
        $refunded = $this->makeReturn(null, 'approved');
        $refunded->forceFill(['approved_by' => $admin->id, 'approved_at' => now(), 'refunded_at' => now()])->save();
        $rejected = $this->makeReturn(null, 'rejected');
        $rejected->forceFill(['approved_by' => $admin->id, 'approved_at' => now(), 'note' => 'Ditolak karena X'])->save();

        foreach (['pending', 'approved', 'refunded', 'rejected', 'all'] as $tab) {
            $this->actingAs($admin)->get(route('admin.returns.index', ['tab' => $tab]))->assertOk();
        }
        $this->actingAs($admin)->get(route('admin.returns.index', ['tab' => 'pending']))->assertSee($pending->order->order_number)->assertDontSee($rejected->order->order_number);

        foreach ([$pending, $approved, $refunded, $rejected] as $r) {
            $this->actingAs($admin)->get(route('admin.returns.show', $r->id))->assertOk()->assertSee($r->order->order_number);
        }
        $this->actingAs($admin)->get(route('admin.returns.show', $approved->id))->assertSee('Tandai Refund Selesai');
        $this->actingAs($admin)->get(route('admin.returns.show', $pending->id))->assertSee('Setujui Retur')->assertSee('Tolak Retur');
    }

    // ---------- keuangan & audit ----------

    public function test_approved_refunds_reduce_dashboard_and_report_revenue(): void
    {
        $admin = $this->userAs('admin');
        $order = $this->completedOrder();                      // total 8.000.000, status selesai
        $r = $this->makeReturn($order);

        $dash = fn () => $this->actingAs($admin)->get(route('admin.dashboard'));
        $dash()->assertOk()->assertSee('8.000.000')->assertDontSee('setelah refund');

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id), ['refund_amount' => 3000000]);

        $dash()->assertOk()->assertSee('5.000.000')->assertSee('setelah refund Rp 3.000.000');
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('refund − Rp 3.000.000')->assertSee('bersih Rp 5.000.000');
    }

    public function test_rejected_or_pending_returns_do_not_affect_revenue(): void
    {
        $admin = $this->userAs('admin');
        $this->makeReturn($this->completedOrder());                                      // pending
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('8.000.000')->assertDontSee('setelah refund');

        $r = ReturnOrder::first();
        $this->actingAs($admin)->post(route('admin.returns.reject', $r->id), ['note' => 'tidak layak']);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('8.000.000')->assertDontSee('setelah refund');
    }

    public function test_dashboard_counts_pending_returns_and_decisions_are_logged(): void
    {
        $admin = $this->userAs('admin');
        $r = $this->makeReturn();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Retur menunggu keputusan');

        $this->actingAs($admin)->post(route('admin.returns.approve', $r->id));
        $this->assertTrue(Activity::where('log_name', 'retur')->where('causer_id', $admin->id)->exists());
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('pengajuan retur menunggu');
    }
}
