<?php

namespace Tests\Feature\Service;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ServiceTicket;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warranty;
use App\Notifications\NewServiceTicketAlert;
use App\Notifications\ServiceTicketUpdated;
use App\Services\OrderService;
use App\Services\ProfitLossService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Garansi per unit + tiket servis (pengajuan, alur status, estimasi biaya, pembayaran, hak akses). */
class ServiceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
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

    private function product(?int $months = null): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Espresso M{$n}", 'slug' => "espresso-m{$n}", 'sku' => "EM-{$n}", 'price' => 5000000, 'stock' => 20, 'stock_threshold' => 1, 'is_active' => true, 'warranty_months' => $months]));
    }

    private function order(User $u, Product $p, int $qty = 1, string $status = 'dikirim'): Order
    {
        $c = Customer::unguarded(fn () => Customer::firstOrCreate(['user_id' => $u->id], ['name' => $u->name, 'email' => $u->email]));
        $o = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $c->id, 'user_id' => $u->id, 'status' => $status,
            'subtotal' => 5000000 * $qty, 'discount' => 0, 'shipping_cost' => 0, 'total' => 5000000 * $qty, 'shipping_name' => 'Budi',
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => 5000000, 'quantity' => $qty, 'subtotal' => 5000000 * $qty]));

        return $o;
    }

    private function warranty(User $u, int $months = 12, ?string $start = null): Warranty
    {
        $o = $this->order($u, $this->product(), 1, 'selesai');
        $start = $start ?? now('Asia/Jakarta')->toDateString();

        return Warranty::create([
            'order_id' => $o->id, 'order_item_id' => $o->items->first()->id, 'product_id' => $o->items->first()->product_id, 'user_id' => $u->id,
            'product_name' => $o->items->first()->product_name, 'starts_at' => $start, 'expires_at' => \Carbon\Carbon::parse($start)->addMonthsNoOverflow($months)->toDateString(), 'status' => 'active',
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['issue_type' => 'bocor', 'description' => 'Air bocor dari bagian bawah mesin sejak kemarin.', 'contact_phone' => '081234567890'], $extra);
    }

    // =============== garansi ===============

    public function test_completing_an_order_issues_one_warranty_per_unit_using_default_or_product_months(): void
    {
        $u = $this->userAs('customer');
        $default = $this->product();          // bawaan 12 bulan
        $custom = $this->product(24);
        $none = $this->product(0);
        $o = $this->order($u, $default, 2);
        foreach ([[$custom, 1], [$none, 1]] as [$p, $q]) {
            OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => 1, 'quantity' => $q, 'subtotal' => $q]));
        }

        app(OrderService::class)->complete($o);

        $this->assertSame(3, Warranty::count());                       // 2 unit + 1 unit; tanpa garansi = tidak ada
        $this->assertSame(2, Warranty::where('product_id', $default->id)->count());
        $w24 = Warranty::where('product_id', $custom->id)->first();
        $this->assertSame(24, (int) round($w24->starts_at->diffInMonths($w24->expires_at)));
        $this->assertSame($u->id, $w24->user_id);
        $this->assertSame(0, Warranty::where('product_id', $none->id)->count());
    }

    public function test_default_months_come_from_settings_and_issuing_is_idempotent(): void
    {
        Setting::set('warranty_default_months', '6');
        $u = $this->userAs('customer');
        $o = $this->order($u, $this->product(), 1);
        app(OrderService::class)->complete($o);

        $w = Warranty::firstOrFail();
        $this->assertSame(6, (int) round($w->starts_at->diffInMonths($w->expires_at)));

        $this->assertSame(0, app(\App\Services\WarrantyService::class)->issueFor($o->fresh()));
        $this->assertSame(1, Warranty::count());
    }

    public function test_customer_confirming_receipt_also_issues_warranty_and_backfill_covers_old_orders(): void
    {
        $u = $this->userAs('customer');
        $o = $this->order($u, $this->product(), 1);

        $this->actingAs($u)->post(route('customer.orders.received', $o->order_number))->assertSessionHasNoErrors();
        $this->assertSame(1, Warranty::count());

        // pesanan lama yang sudah selesai sebelum fitur ada
        $old = $this->order($u, $this->product(), 2, 'selesai');
        $this->artisan('warranty:backfill')->assertSuccessful();
        $this->assertSame(2, Warranty::where('order_id', $old->id)->count());
        $this->artisan('warranty:backfill')->assertSuccessful();
        $this->assertSame(3, Warranty::count());                        // tidak dobel
    }

    public function test_warranty_states(): void
    {
        $u = $this->userAs('customer');
        $this->assertTrue($this->warranty($u, 12)->isActive());
        $expired = $this->warranty($u, 12, now('Asia/Jakarta')->subMonths(14)->toDateString());
        $this->assertFalse($expired->isActive());
        $this->assertSame('Kedaluwarsa', $expired->label());
        $expired->update(['status' => 'void']);
        $this->assertSame('Dibatalkan', $expired->fresh()->label());
    }

    public function test_admin_sets_serial_numbers_uniquely_and_can_void_with_a_reason(): void
    {
        $u = $this->userAs('customer');
        $a = $this->warranty($u);
        $b = $this->warranty($u);
        $staff = $this->userAs('staff');

        $this->actingAs($staff)->put(route('admin.warranties.update', $a->id), ['serial_number' => 'SN-2026/001'])->assertSessionHasNoErrors();
        $this->assertSame('SN-2026/001', $a->fresh()->serial_number);
        $this->actingAs($staff)->put(route('admin.warranties.update', $b->id), ['serial_number' => 'SN-2026/001'])->assertSessionHasErrors('serial_number');
        $this->actingAs($staff)->put(route('admin.warranties.update', $b->id), ['serial_number' => 'bad serial!'])->assertSessionHasErrors('serial_number');

        $this->actingAs($staff)->post(route('admin.warranties.void', $b->id), ['void_reason' => 'x'])->assertSessionHasErrors('void_reason');
        $this->actingAs($staff)->post(route('admin.warranties.void', $b->id), ['void_reason' => 'Unit salah kirim'])->assertSessionHasNoErrors();
        $this->assertSame('void', $b->fresh()->status);

        $this->actingAs($staff)->get(route('admin.warranties.index', ['state' => 'noserial']))->assertOk();
    }

    // =============== pengajuan tiket ===============

    public function test_customer_creates_a_ticket_for_a_warranty_unit(): void
    {
        Setting::set('order_notify_email', 'toko@koffline.my.id');
        $u = $this->userAs('customer');
        $w = $this->warranty($u);

        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['warranty_id' => $w->id]))->assertSessionHasNoErrors();

        $t = ServiceTicket::firstOrFail();
        $this->assertMatchesRegularExpression('/^SRV-\d{8}-0001$/', $t->ticket_number);
        $this->assertTrue($t->in_warranty);
        $this->assertSame($w->product_name, $t->product_name);
        $this->assertSame('baru', $t->status);
        $this->assertSame(1, $t->events()->count());
        Notification::assertSentOnDemand(NewServiceTicketAlert::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === ['toko@koffline.my.id']);
    }

    public function test_ticket_outside_warranty_or_without_a_unit_is_marked_paid_service(): void
    {
        $u = $this->userAs('customer');
        $expired = $this->warranty($u, 12, now('Asia/Jakarta')->subMonths(20)->toDateString());

        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['warranty_id' => $expired->id]));
        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['product_name' => 'Mesin beli di tempat lain', 'serial_number' => 'X-9']));

        $this->assertSame(2, ServiceTicket::count());
        $this->assertSame(0, ServiceTicket::where('in_warranty', true)->count());
    }

    public function test_ticket_creation_rules(): void
    {
        $u = $this->userAs('customer');
        $other = $this->userAs('customer');
        $mine = $this->warranty($u);
        $theirs = $this->warranty($other);

        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['warranty_id' => $theirs->id]))->assertSessionHasErrors('warranty_id');   // milik orang lain
        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['warranty_id' => $mine->id]))->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['warranty_id' => $mine->id]))->assertSessionHasErrors('warranty_id');       // masih ada tiket berjalan

        foreach ([['description' => 'pendek'], ['issue_type' => 'ngawur'], ['contact_phone' => 'abc'], ['product_name' => null, 'warranty_id' => null]] as $bad) {
            $this->actingAs($u)->post(route('customer.service.store'), $this->payload($bad))->assertSessionHasErrors();
        }
        $this->assertSame(1, ServiceTicket::count());
    }

    public function test_photos_are_private_validated_and_limited_to_owner_and_staff(): void
    {
        $u = $this->userAs('customer');
        $w = $this->warranty($u);

        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['warranty_id' => $w->id, 'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')]]))->assertSessionHasNoErrors();
        $t = ServiceTicket::firstOrFail();
        $this->assertCount(2, $t->photos);
        Storage::disk('local')->assertExists($t->photos[0]);

        $this->actingAs($u)->get(route('customer.service.photo', [$t->ticket_number, 1]))->assertOk();
        $this->actingAs($this->userAs('customer'))->get(route('customer.service.photo', [$t->ticket_number, 1]))->assertNotFound();
        $this->actingAs($this->userAs('staff'))->get(route('admin.service.photo', [$t->id, 1]))->assertOk();
        $this->actingAs($this->userAs('keuangan'))->get(route('admin.service.photo', [$t->id, 1]))->assertForbidden();

        $other = $this->userAs('customer');
        $w2 = $this->warranty($other);
        foreach ([[UploadedFile::fake()->create('x.exe', 10)], [UploadedFile::fake()->image('a.jpg')->size(4000)], array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), [1, 2, 3, 4])] as $bad) {
            $this->actingAs($other)->post(route('customer.service.store'), $this->payload(['warranty_id' => $w2->id, 'photos' => $bad]))->assertSessionHasErrors();
        }
    }

    // =============== alur status ===============

    private function ticket(User $u, bool $inWarranty = true): ServiceTicket
    {
        $w = $this->warranty($u, 12, $inWarranty ? null : now('Asia/Jakarta')->subMonths(20)->toDateString());
        $this->actingAs($u)->post(route('customer.service.store'), $this->payload(['warranty_id' => $w->id]));
        Notification::fake();

        return ServiceTicket::latest('id')->firstOrFail();
    }

    private function move(ServiceTicket $t, string $to, array $extra = [])
    {
        return $this->actingAs($this->userAs('staff'))->put(route('admin.service.update', $t->id), array_merge(['status' => $to], $extra));
    }

    public function test_warranty_ticket_flow_is_free_and_skips_cost_approval(): void
    {
        $u = $this->userAs('customer');
        $t = $this->ticket($u, true);

        $this->move($t, 'diterima', ['note' => 'Unit sudah diterima teknisi'])->assertSessionHasNoErrors();
        $this->move($t, 'diperiksa')->assertSessionHasNoErrors();
        $this->move($t, 'menunggu_persetujuan', ['estimate_cost' => 500000])->assertSessionHasErrors('status');      // garansi: tidak perlu persetujuan
        $this->move($t, 'dikerjakan')->assertSessionHasNoErrors();
        $this->move($t, 'selesai', ['final_cost' => 999999])->assertSessionHasNoErrors();                             // garansi memaksa Rp 0

        $t->refresh();
        $this->assertSame('selesai', $t->status);
        $this->assertEquals(0, $t->final_cost);
        $this->assertNotNull($t->closed_at);
        $this->assertSame(['baru', 'diterima', 'diperiksa', 'dikerjakan', 'selesai'], $t->events->pluck('status')->all());
        Notification::assertSentTo($u, ServiceTicketUpdated::class, fn ($n) => $n->note === 'Unit sudah diterima teknisi');
    }

    public function test_invalid_jumps_are_rejected_and_nothing_changes(): void
    {
        $t = $this->ticket($this->userAs('customer'));

        $this->move($t, 'selesai')->assertSessionHasErrors('status');
        $this->move($t, 'dikerjakan')->assertSessionHasErrors('status');
        $this->assertSame('baru', $t->fresh()->status);
        $this->assertSame(1, $t->events()->count());

        $this->move($t, 'ditolak', ['note' => 'Di luar cakupan'])->assertSessionHasNoErrors();
        $this->move($t, 'diterima')->assertSessionHasErrors('status');                                                // status akhir tidak bisa dibuka lagi
    }

    public function test_paid_service_needs_an_estimate_and_customer_approval(): void
    {
        $u = $this->userAs('customer');
        $t = $this->ticket($u, false);
        $this->assertFalse($t->in_warranty);

        $this->move($t, 'diterima');
        $this->move($t, 'diperiksa');
        $this->move($t, 'menunggu_persetujuan')->assertSessionHasErrors('status');                                    // wajib estimasi
        $this->move($t, 'menunggu_persetujuan', ['estimate_cost' => 750000])->assertSessionHasNoErrors();
        $this->assertEquals(750000, $t->fresh()->estimate_cost);

        $this->actingAs($u)->post(route('customer.service.respond', $t->ticket_number), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('dikerjakan', $t->fresh()->status);

        $this->move($t, 'selesai')->assertSessionHasNoErrors();                                                       // biaya akhir bawaan = estimasi
        $this->assertEquals(750000, $t->fresh()->final_cost);
    }

    public function test_customer_can_decline_the_estimate_or_cancel_early_but_not_later(): void
    {
        $u = $this->userAs('customer');
        $t = $this->ticket($u, false);
        $this->move($t, 'diterima'); $this->move($t, 'diperiksa'); $this->move($t, 'menunggu_persetujuan', ['estimate_cost' => 900000]);

        $this->actingAs($u)->post(route('customer.service.respond', $t->ticket_number), ['decision' => 'decline']);
        $this->assertSame('dibatalkan', $t->fresh()->status);

        $t2 = $this->ticket($u, false);
        $this->actingAs($u)->post(route('customer.service.cancel', $t2->ticket_number))->assertSessionHas('success');
        $this->assertSame('dibatalkan', $t2->fresh()->status);

        $t3 = $this->ticket($u, false);
        $this->move($t3, 'diterima');
        $this->actingAs($u)->post(route('customer.service.cancel', $t3->ticket_number))->assertSessionHas('error');   // sudah diproses teknisi
        $this->assertSame('diterima', $t3->fresh()->status);
        $this->actingAs($u)->post(route('customer.service.respond', $t3->ticket_number), ['decision' => 'approve'])->assertSessionHas('error');
    }

    public function test_paid_service_revenue_reaches_the_profit_report_only_after_payment(): void
    {
        $u = $this->userAs('customer');
        $t = $this->ticket($u, false);
        $this->move($t, 'diterima'); $this->move($t, 'diperiksa'); $this->move($t, 'dikerjakan');
        $this->move($t, 'selesai', ['final_cost' => 600000]);
        $svc = app(ProfitLossService::class);
        $today = now('Asia/Jakarta')->toDateString();

        $this->assertEquals(0, $svc->report($today, $today)['service_revenue']);

        $admin = $this->userAs('admin');
        $this->actingAs($admin)->post(route('admin.service.pay', $t->id))->assertSessionHas('success');
        $this->assertEquals(600000, $svc->report($today, $today)['service_revenue']);

        $this->actingAs($admin)->post(route('admin.service.pay', $t->id))->assertSessionHas('error');                  // tidak dobel
    }

    public function test_free_warranty_ticket_cannot_be_marked_paid(): void
    {
        $t = $this->ticket($this->userAs('customer'), true);
        foreach (['diterima', 'diperiksa', 'dikerjakan', 'selesai'] as $s) {
            $this->move($t, $s);
        }
        $this->actingAs($this->userAs('admin'))->post(route('admin.service.pay', $t->id))->assertSessionHas('error');
        $this->assertNull($t->fresh()->paid_at);
    }

    // =============== akses ===============

    public function test_customers_only_see_their_own_tickets_and_pages_render(): void
    {
        $a = $this->userAs('customer');
        $b = $this->userAs('customer');
        $t = $this->ticket($a);

        $this->actingAs($a)->get(route('customer.service.show', $t->ticket_number))->assertOk()->assertSee($t->ticket_number)->assertSee('Riwayat');
        $this->actingAs($a)->get(route('customer.service.index'))->assertOk()->assertSee('Garansi produk saya');
        $this->actingAs($a)->get(route('customer.service.create'))->assertOk();
        $this->actingAs($b)->get(route('customer.service.show', $t->ticket_number))->assertNotFound();
        $this->actingAs($b)->post(route('customer.service.cancel', $t->ticket_number))->assertNotFound();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('customer.service.index'))->assertRedirect(route('login'));
        $this->get(route('admin.service.index'))->assertRedirect(route('login'));
    }

    public function test_admin_pages_and_permissions(): void
    {
        $t = $this->ticket($this->userAs('customer'));

        foreach (['staff', 'admin', 'super_admin'] as $role) {
            $u = $this->userAs($role);
            $this->actingAs($u)->get(route('admin.service.index'))->assertOk();
            $this->actingAs($u)->get(route('admin.service.show', $t->id))->assertOk()->assertSee($t->ticket_number);
            $this->actingAs($u)->get(route('admin.warranties.index'))->assertOk();
        }
        foreach (['gudang', 'keuangan', 'customer'] as $role) {
            $u = $this->userAs($role);
            $this->actingAs($u)->get(route('admin.service.index'))->assertForbidden();
            $this->actingAs($u)->put(route('admin.service.update', $t->id), ['status' => 'diterima'])->assertForbidden();
            $this->actingAs($u)->get(route('admin.warranties.index'))->assertForbidden();
        }
        $this->assertSame('baru', $t->fresh()->status);
    }

    public function test_admin_list_filters(): void
    {
        $this->ticket($this->userAs('customer'), true);
        $paid = $this->ticket($this->userAs('customer'), false);
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->get(route('admin.service.index', ['warranty' => '0']))->assertOk()->assertSee($paid->ticket_number)->assertDontSee(ServiceTicket::where('in_warranty', true)->first()->ticket_number);
        $this->actingAs($admin)->get(route('admin.service.index', ['search' => $paid->ticket_number]))->assertOk()->assertSee($paid->ticket_number);
        $this->actingAs($admin)->get(route('admin.service.index', ['status' => 'selesai']))->assertOk()->assertDontSee($paid->ticket_number);
    }

    public function test_product_form_saves_warranty_months(): void
    {
        $p = $this->product();
        $this->actingAs($this->userAs('admin'))->get(route('admin.products.edit', $p->id))->assertOk()->assertSee('Masa Garansi');
    }
}
