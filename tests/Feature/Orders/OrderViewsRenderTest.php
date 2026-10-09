<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Smoke test: view yang berubah harus ter-render di setiap status order. */
class OrderViewsRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status, ?string $paymentStatus = null): Order
    {
        $user = User::factory()->create();
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => 'Budi', 'email' => $user->email]));
        $p = Product::unguarded(fn () => Product::create(['name' => 'Mesin X', 'slug' => 'mesin-x-' . uniqid(), 'sku' => 'RS-' . uniqid(), 'price' => 1000, 'stock' => 9]));

        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id,
            'status' => $status, 'subtotal' => 1000, 'total' => 1000,
            'shipping_name' => 'Budi', 'shipping_phone' => '0812', 'shipping_address' => 'Jl. Kopi 1, Jakarta',
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'product_name' => 'Mesin X', 'product_sku' => $p->sku, 'price' => 1000, 'quantity' => 1, 'subtotal' => 1000]));

        if ($paymentStatus) {
            Payment::unguarded(fn () => Payment::create(['order_id' => $order->id, 'method' => 'transfer_bca', 'amount' => 1000, 'status' => $paymentStatus, 'note' => 'Buram']));
        }

        return $order;
    }

    public static function states(): array
    {
        return [
            'baru'            => ['menunggu_bayar', null],
            'bukti menunggu'  => ['menunggu_bayar', 'pending'],
            'bukti ditolak'   => ['menunggu_bayar', 'rejected'],
            'dikonfirmasi'    => ['dikonfirmasi', 'verified'],
            'dikirim'         => ['dikirim', 'verified'],
            'selesai'         => ['selesai', 'verified'],
            'batal'           => ['batal', null],
        ];
    }

    /** @dataProvider states */
    public function test_customer_order_page_renders(string $status, ?string $pay): void
    {
        $order = $this->makeOrder($status, $pay);

        $res = $this->actingAs($order->user)->get(route('customer.orders.show', $order->order_number));
        $res->assertOk();

        if ($pay === 'rejected') {
            $res->assertSee('Bukti pembayaran ditolak')->assertSee('name="proof_image"', false); // bisa upload ulang
        }
        if ($pay === 'pending') {
            $res->assertSee('Ganti Bukti Pembayaran');
        }
        if ($status === 'dikirim') {
            $res->assertSee('Pesanan Sudah Diterima');
        }
    }

    /** @dataProvider states */
    public function test_admin_order_page_renders_with_address_snapshot(string $status, ?string $pay): void
    {
        $order = $this->makeOrder($status, $pay);
        $this->seed([\Database\Seeders\RoleSeeder::class, \Database\Seeders\PermissionSeeder::class, \Database\Seeders\RolePermissionSeeder::class]);
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)->get(route('admin.orders.show', $order->id))
            ->assertOk()->assertSee('Jl. Kopi 1, Jakarta');
    }

    public function test_cart_and_checkout_pages_render(): void
    {
        $user = User::factory()->create();
        $p = Product::unguarded(fn () => Product::create(['name' => 'Mesin Y', 'slug' => 'mesin-y', 'sku' => 'RS-Y', 'price' => 1000, 'stock' => 9]));
        $session = ['cart' => [$p->id => ['id' => $p->id, 'name' => 'Mesin Y', 'slug' => 'mesin-y', 'sku' => 'RS-Y', 'price' => 500, 'quantity' => 99, 'image' => null, 'stock' => 9]]];

        // harga lama (500) & qty melebihi stok -> disegarkan + ada peringatan
        $this->withSession($session)->get(route('customer.cart.index'))->assertOk()->assertSessionHas('warning');

        $this->actingAs($user)->withSession($session)->get(route('customer.checkout.index'))
            ->assertOk()->assertSee('name="idempotency_key"', false);
    }
}
