<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MakesRegions;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase, MakesRegions;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->service = app(OrderService::class);
        // Role + permission asli (matriks PRD), supaya tes akses sesuai kenyataan.
        $this->seed([\Database\Seeders\RoleSeeder::class, \Database\Seeders\PermissionSeeder::class, \Database\Seeders\RolePermissionSeeder::class]);
    }

    // ---------- helpers ----------

    private function product(int $stock = 10, float $price = 1000000, bool $active = true): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create([
            'name' => "Mesin {$n}", 'slug' => "mesin-{$n}", 'sku' => "RS-ESP-{$n}",
            'price' => $price, 'stock' => $stock, 'stock_threshold' => 5, 'is_active' => $active,
        ]));
    }

    private function order(Product $product, int $qty = 2, string $status = 'menunggu_bayar', bool $withPendingPayment = true): Order
    {
        $user = User::factory()->create();
        $customer = Customer::unguarded(fn () => Customer::create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
        ]));

        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(),
            'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => $status,
            'subtotal' => $product->price * $qty, 'total' => $product->price * $qty,
        ]));

        OrderItem::unguarded(fn () => OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'product_sku' => $product->sku, 'price' => $product->price, 'quantity' => $qty,
            'subtotal' => $product->price * $qty,
        ]));

        if ($withPendingPayment) {
            Payment::unguarded(fn () => Payment::create([
                'order_id' => $order->id, 'method' => 'transfer_bca', 'amount' => $order->total, 'status' => 'pending',
            ]));
        }

        return $order;
    }

    // ---------- stok & status ----------

    public function test_stock_is_not_reduced_at_checkout_but_when_payment_is_confirmed(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 3);

        $this->assertSame(10, $p->fresh()->stock);

        $this->service->confirmPayment($order, 1);

        $this->assertSame(7, $p->fresh()->stock);
        $this->assertSame('dikonfirmasi', $order->fresh()->status);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'out', 'quantity' => 3]);
    }

    public function test_confirming_twice_never_deducts_stock_twice(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 3);

        $this->service->confirmPayment($order, 1);

        $this->expectException(DomainException::class);
        try {
            $this->service->confirmPayment($order, 1);
        } finally {
            $this->assertSame(7, $p->fresh()->stock);
        }
    }

    public function test_confirm_is_rejected_and_rolled_back_when_stock_is_insufficient(): void
    {
        $p = $this->product(1);
        $order = $this->order($p, 3);

        try {
            $this->service->confirmPayment($order, 1);
            $this->fail('Seharusnya ditolak karena stok kurang.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }

        $this->assertSame(1, $p->fresh()->stock);
        $this->assertSame('menunggu_bayar', $order->fresh()->status);
        $this->assertSame('pending', $order->payment()->first()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cancel_after_confirmation_restores_stock(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 4);
        $this->service->confirmPayment($order, 1);
        $this->assertSame(6, $p->fresh()->stock);

        $this->service->cancel($order, 'test', 1);

        $this->assertSame(10, $p->fresh()->stock);
        $this->assertSame('batal', $order->fresh()->status);
    }

    public function test_cancel_before_payment_does_not_touch_stock(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 4);

        $this->service->cancel($order, 'test', 1);

        $this->assertSame(10, $p->fresh()->stock);
        $this->assertSame('batal', $order->fresh()->status);
        $this->assertSame('rejected', $order->payment()->first()->status); // antrean verifikasi ikut bersih
    }

    public function test_status_only_moves_forward(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 1);

        $this->expectException(DomainException::class);
        $this->service->ship($order, ['courier' => 'JNE', 'tracking_number' => 'X1']); // belum dikonfirmasi
    }

    public function test_full_happy_path_to_completed(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 1);

        $this->service->confirmPayment($order, 1);
        $this->service->process($order);
        $this->service->ship($order, ['courier' => 'JNE', 'tracking_number' => 'RESI123']);
        $this->service->complete($order);

        $this->assertSame('selesai', $order->fresh()->status);
        $this->assertDatabaseHas('shipments', ['order_id' => $order->id, 'tracking_number' => 'RESI123', 'status' => 'delivered']);
    }

    public function test_shipped_order_cannot_be_cancelled(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 1);
        $this->service->confirmPayment($order, 1);
        $this->service->ship($order, ['courier' => 'JNE', 'tracking_number' => 'R']);

        $this->expectException(DomainException::class);
        $this->service->cancel($order, 'x', 1);
    }

    // ---------- auto-cancel 24 jam ----------

    public function test_unpaid_orders_older_than_24h_are_auto_cancelled(): void
    {
        $p = $this->product(10);
        $old = $this->order($p, 1, withPendingPayment: false);
        $old->forceFill(['created_at' => now()->subHours(25)])->save();

        $fresh = $this->order($p, 1, withPendingPayment: false);

        $this->assertSame(1, $this->service->cancelExpired());
        $this->assertSame('batal', $old->fresh()->status);
        $this->assertSame('menunggu_bayar', $fresh->fresh()->status);
    }

    public function test_order_with_proof_awaiting_review_is_not_auto_cancelled(): void
    {
        $p = $this->product(10);
        $old = $this->order($p, 1, withPendingPayment: true);
        $old->forceFill(['created_at' => now()->subHours(30)])->save();

        $this->assertSame(0, $this->service->cancelExpired());
        $this->assertSame('menunggu_bayar', $old->fresh()->status);
    }

    // ---------- HTTP: cart, checkout, akses ----------

    private function checkoutPayload(string $key): array
    {
        return [
            'idempotency_key' => $key, 'name' => 'Budi', 'phone' => '0812', 'address' => 'Jl. Kopi 1',
            'province_id' => $this->region()[0]->id, 'city_id' => $this->region()[1]->id, 'postal_code' => '40111',
        ];
    }

    public function test_guest_can_add_to_cart_but_checkout_requires_login(): void
    {
        $p = $this->product(5);

        $this->post(route('customer.cart.store'), ['product_id' => $p->id, 'quantity' => 2])
            ->assertRedirect(route('customer.cart.index'));
        $this->assertSame(2, session('cart')[$p->id]['quantity']);

        $this->get(route('customer.checkout.index'))->assertRedirect(route('login'));
    }

    public function test_cart_rejects_quantity_above_stock_and_inactive_products(): void
    {
        $p = $this->product(2);
        $off = $this->product(5, active: false);

        $this->post(route('customer.cart.store'), ['product_id' => $p->id, 'quantity' => 3])->assertSessionHas('error');
        $this->post(route('customer.cart.store'), ['product_id' => $off->id, 'quantity' => 1])->assertSessionHas('error');
        $this->assertEmpty(session('cart', []));
    }

    public function test_double_submit_creates_only_one_order(): void
    {
        $p = $this->product(10, 2500000);
        $user = User::factory()->create();
        $key = 'key-123';

        $session = ['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku,
            'price' => $p->price, 'quantity' => 2, 'image' => null, 'stock' => 10]], 'checkout_key' => $key];

        $this->actingAs($user)->withSession($session)
            ->post(route('customer.checkout.store'), $this->checkoutPayload($key))
            ->assertSessionHasNoErrors();

        // klik kedua dengan kunci yang sama
        $this->actingAs($user)->withSession($session)
            ->post(route('customer.checkout.store'), $this->checkoutPayload($key));

        $this->assertSame(1, Order::count());

        $order = Order::first();
        $this->assertSame('5000000.00', (string) $order->total);
        $this->assertSame($p->sku, $order->items()->first()->product_sku);   // SKU, bukan slug
        $this->assertSame(10, $p->fresh()->stock);                            // stok belum berkurang
        $this->assertSame('Jl. Kopi 1', $order->shipping_address);
        $this->assertStringContainsString('Kota Bandung', $order->full_shipping_address);
        $this->assertStringContainsString('Jawa Barat 40111', $order->full_shipping_address);
    }

    public function test_checkout_uses_database_price_not_stale_session_price(): void
    {
        $p = $this->product(10, 2000000);
        $user = User::factory()->create();
        $key = 'k2';

        $session = ['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku,
            'price' => 1, 'quantity' => 1, 'image' => null, 'stock' => 10]], 'checkout_key' => $key];

        // harga di session basi -> diminta cek ulang, order TIDAK dibuat dengan harga palsu
        $this->actingAs($user)->withSession($session)
            ->post(route('customer.checkout.store'), $this->checkoutPayload($key))
            ->assertRedirect(route('customer.checkout.index'));

        $this->assertSame(0, Order::count());
    }

    public function test_address_snapshot_does_not_change_when_profile_changes(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 1);
        $order->update(['shipping_address' => 'Alamat Lama 1']);

        $order->customer->update(['address' => 'Alamat Baru 99']);

        $this->assertSame('Alamat Lama 1', $order->fresh()->shipping_address);
    }

    public function test_customer_cannot_cancel_a_paid_order(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 1);
        $this->service->confirmPayment($order, 1);

        $this->actingAs($order->user)
            ->post(route('customer.orders.cancel', $order->order_number))
            ->assertSessionHas('error');

        $this->assertSame('dikonfirmasi', $order->fresh()->status);
    }

    public function test_customer_cannot_touch_someone_elses_order(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 1);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('customer.orders.show', $order->order_number))->assertNotFound();
        $this->actingAs($stranger)->post(route('customer.orders.cancel', $order->order_number))->assertNotFound();
    }

    public function test_staff_can_open_orders_but_not_users_or_create_products(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.products.index'))->assertOk();      // staff: view_products
        $this->actingAs($staff)->get(route('admin.products.create'))->assertForbidden(); // tanpa create_products
        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_customer_role_is_forbidden_from_admin_panel(): void
    {
        $c = User::factory()->create();
        $c->assignRole('customer');

        $this->actingAs($c)->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_invoice_pdf_is_generated(): void
    {
        $p = $this->product(10);
        $order = $this->order($p, 1);

        $res = $this->actingAs($order->user)->get(route('customer.orders.invoice', $order->order_number));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_rejected_payment_can_be_reuploaded_and_history_is_kept(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        $p = $this->product(10);
        $order = $this->order($p, 1);
        $this->service->rejectPayment($order->payment()->first(), 'Buram', 1);

        $this->actingAs($order->user)
            ->post(route('customer.orders.pay', $order->order_number), [
                'method' => 'transfer_bca',
                'proof_image' => \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg'),
                'amount' => 1, // diabaikan server
            ])->assertSessionHas('success');

        $this->assertSame(2, $order->payments()->count());
        $latest = $order->payments()->latest('id')->first();
        $this->assertSame('pending', $latest->status);
        $this->assertSame((string) $order->total, (string) $latest->amount);
    }
}
