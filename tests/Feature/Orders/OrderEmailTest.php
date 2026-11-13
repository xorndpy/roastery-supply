<?php

namespace Tests\Feature\Orders;

use App\Models\City;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Province;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewOrderAlert;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Services\OrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Email "pesanan baru" ke pembeli dan toko, tanpa menunggu Midtrans/pembayaran. */
class OrderEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function product(): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Mesin Kopi {$n}", 'slug' => "mesin-kopi-{$n}", 'sku' => "MK-{$n}", 'price' => 2500000, 'stock' => 20, 'stock_threshold' => 1, 'is_active' => true]));
    }

    /** Checkout nyata lewat HTTP (tanpa konfigurasi ongkir, seperti di server demo). */
    private function checkout(User $user, Product $p): \Illuminate\Testing\TestResponse
    {
        $prov = Province::firstOrCreate(['code' => '32'], ['name' => 'Jawa Barat']);
        $city = City::firstOrCreate(['code' => '32.73'], ['province_id' => $prov->id, 'name' => 'Kota Bandung']);

        return $this->actingAs($user)
            ->withSession(['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => 2, 'weight' => 1000, 'image' => null, 'stock' => $p->stock]], 'checkout_key' => 'k1'])
            ->post(route('customer.checkout.store'), [
                'idempotency_key' => 'k1', 'name' => 'Budi', 'phone' => '0812', 'address' => 'Jl. Kopi 1',
                'province_id' => $prov->id, 'city_id' => $city->id, 'postal_code' => '40111',
            ]);
    }

    private function customer(): User
    {
        $u = User::factory()->create(['name' => 'Budi']);
        $u->assignRole('customer');

        return $u;
    }

    // ---------- pesanan baru ----------

    public function test_checkout_emails_the_customer_and_the_store_even_though_nothing_is_paid(): void
    {
        Notification::fake();
        Setting::set('order_notify_email', 'toko@koffline.my.id');
        $user = $this->customer();

        $this->checkout($user, $this->product())->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame('menunggu_bayar', $order->status); // belum dibayar sama sekali

        Notification::assertSentTo($user, OrderPlaced::class, fn ($n) => $n->order->is($order));
        Notification::assertSentOnDemand(NewOrderAlert::class, function ($n, $channels, $notifiable) use ($order) {
            return $n->event === 'baru' && $n->order->is($order) && $notifiable->routes['mail'] === ['toko@koffline.my.id'];
        });
        Notification::assertNotSentTo($user, OrderStatusChanged::class); // tidak ada email "pembayaran dikonfirmasi" palsu
    }

    public function test_store_email_supports_several_recipients_and_ignores_invalid_ones(): void
    {
        Notification::fake();
        Setting::set('order_notify_email', 'a@koffline.my.id, bukan-email ; b@koffline.my.id,, a@koffline.my.id');

        $this->checkout($this->customer(), $this->product());

        Notification::assertSentOnDemand(NewOrderAlert::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === ['a@koffline.my.id', 'b@koffline.my.id']);
    }

    public function test_no_store_email_is_sent_without_a_configured_address_and_never_falls_back_to_public_contact_email(): void
    {
        Notification::fake();
        Setting::set('contact_email', 'admin@KOFFLINE.com'); // email publik di footer BUKAN penerima notifikasi
        $user = $this->customer();

        $this->checkout($user, $this->product());

        Notification::assertNothingSentTo(new AnonymousNotifiable());
        Notification::assertCount(1); // hanya email ke pembeli
        Notification::assertSentTo($user, OrderPlaced::class);
    }

    public function test_a_mail_failure_never_breaks_checkout(): void
    {
        Setting::set('order_notify_email', 'toko@koffline.my.id');
        $this->mock(Dispatcher::class)->shouldReceive('send')->andThrow(new \RuntimeException('SMTP down'));

        $res = $this->checkout($this->customer(), $this->product());

        $res->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
        $res->assertRedirect(route('customer.orders.show', Order::first()->order_number));
    }

    // ---------- pembayaran masuk ----------

    public function test_store_is_told_when_payment_is_confirmed_and_customer_still_gets_theirs(): void
    {
        Setting::set('order_notify_email', 'toko@koffline.my.id');
        $user = $this->customer();
        $p = $this->product();
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'user_id' => $user->id, 'status' => 'menunggu_bayar',
            'subtotal' => 5000000, 'discount' => 0, 'shipping_cost' => 0, 'total' => 5000000, 'shipping_name' => 'Budi',
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => 2500000, 'quantity' => 2, 'subtotal' => 5000000]));
        Payment::unguarded(fn () => Payment::create(['order_id' => $order->id, 'method' => 'manual', 'amount' => 5000000, 'status' => 'pending']));
        $admin = User::factory()->create();

        Notification::fake();
        app(OrderService::class)->confirmPayment($order, $admin->id);

        Notification::assertSentTo($user, OrderStatusChanged::class, fn ($n) => $n->event === 'dikonfirmasi');
        Notification::assertSentOnDemand(NewOrderAlert::class, fn ($n) => $n->event === 'dibayar');
    }

    // ---------- isi email ----------

    public function test_email_contents(): void
    {
        $user = $this->customer();
        $p = $this->product();
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => 'ORD-20261112-0001', 'user_id' => $user->id, 'status' => 'menunggu_bayar', 'subtotal' => 5000000, 'discount' => 0,
            'shipping_cost' => 20000, 'total' => 5020000, 'shipping_name' => 'Budi', 'shipping_phone' => '0812', 'shipping_address' => 'Jl. Kopi 1',
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'product_name' => '<b>Grinder</b>', 'product_sku' => $p->sku, 'price' => 2500000, 'quantity' => 2, 'subtotal' => 5000000]));

        $customerMail = (new OrderPlaced($order))->toMail($user);
        $this->assertSame('[ORD-20261112-0001] Pesanan diterima', $customerMail->subject);
        $html = (string) $customerMail->render();
        $this->assertStringContainsString('ORD-20261112-0001', $html);
        $this->assertStringContainsString('Rp 5.020.000', $html);
        $this->assertStringContainsString('Mohon selesaikan pembayaran sebelum', $html);
        $this->assertStringContainsString('&lt;b&gt;Grinder&lt;/b&gt;', $html); // nama produk di-escape
        $this->assertStringContainsString(route('customer.orders.show', 'ORD-20261112-0001'), $html);

        $new = (new NewOrderAlert($order, 'baru'))->toMail(new AnonymousNotifiable());
        $this->assertStringContainsString('[Pesanan baru]', $new->subject);
        $this->assertStringContainsString('Rp 5.020.000', $new->subject);
        $this->assertStringContainsString(route('admin.orders.show', $order->id), (string) $new->render());

        $paid = (new NewOrderAlert($order, 'dibayar'))->toMail(new AnonymousNotifiable());
        $this->assertStringContainsString('[Pembayaran masuk]', $paid->subject);
    }
}
