<?php

namespace Tests\Feature\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Couriers;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Alur setelah bayar: pilih kurir + input resi -> pesanan "dikirim", pelanggan melihat resi. */
class ShipInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function as(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function order(string $status = 'dikonfirmasi', ?string $method = 'JNE REG', ?User $user = null): Order
    {
        $customer = Customer::create(['user_id' => $user?->id, 'name' => 'Budi', 'email' => 'b' . uniqid() . '@example.com', 'phone' => '0811']);

        return Order::unguarded(fn () => Order::create([
            'order_number' => 'ORD-S-' . uniqid(), 'customer_id' => $customer->id, 'user_id' => $user?->id, 'status' => $status,
            'subtotal' => 1000, 'total' => 1000, 'shipping_method' => $method, 'shipping_etd' => '2-3 hari',
        ]));
    }

    public function test_courier_is_guessed_from_customer_choice(): void
    {
        $this->assertSame('JNE', Couriers::guess('JNE REG'));
        $this->assertSame('J&T', Couriers::guess('J&T Express'));
        $this->assertSame('Kurir Toko (antar sendiri)', Couriers::guess('Kurir Toko'));
        $this->assertNull(Couriers::guess('Kargo Reguler'));
        $this->assertNull(Couriers::guess(null));
    }

    public function test_ship_with_listed_courier_normalizes_resi_and_moves_order_to_dikirim(): void
    {
        $o = $this->order();

        $this->actingAs($this->as('staff'))->post(route('admin.orders.ship', $o->id), [
            'courier' => 'JNE', 'service' => 'REG', 'tracking_number' => ' cgk 123 4567 ',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('dikirim', $o->fresh()->status);
        $this->assertDatabaseHas('shipments', ['order_id' => $o->id, 'courier' => 'JNE', 'service' => 'REG', 'tracking_number' => 'CGK1234567', 'status' => 'shipped']);
    }

    public function test_ship_with_other_courier_requires_a_name(): void
    {
        $o = $this->order();
        $staff = $this->as('staff');

        $this->actingAs($staff)->post(route('admin.orders.ship', $o->id), ['courier' => '__other', 'tracking_number' => 'ABC12345', 'ship_order' => $o->id])
            ->assertSessionHasErrors('courier_other');
        $this->assertSame('dikonfirmasi', $o->fresh()->status);

        $this->actingAs($staff)->post(route('admin.orders.ship', $o->id), ['courier' => '__other', 'courier_other' => 'Paxel', 'tracking_number' => 'ABC12345'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shipments', ['order_id' => $o->id, 'courier' => 'Paxel']);
    }

    public function test_bad_or_duplicate_resi_is_rejected_without_changing_the_order(): void
    {
        $first = $this->order();
        $second = $this->order();
        $staff = $this->as('staff');

        $this->actingAs($staff)->post(route('admin.orders.ship', $first->id), ['courier' => 'JNE', 'tracking_number' => 'JNE0001234']);

        $this->actingAs($staff)->post(route('admin.orders.ship', $second->id), ['courier' => 'jne', 'tracking_number' => 'jne0001234'])
            ->assertSessionHasErrors('tracking_number');
        $this->assertSame('dikonfirmasi', $second->fresh()->status);

        $this->actingAs($staff)->post(route('admin.orders.ship', $second->id), ['courier' => 'JNE', 'tracking_number' => 'ab'])
            ->assertSessionHasErrors('tracking_number');
        $this->actingAs($staff)->post(route('admin.orders.ship', $second->id), ['courier' => 'JNE', 'tracking_number' => 'RESI#$%^&*'])
            ->assertSessionHasErrors('tracking_number');

        // Resi yang sama di kurir berbeda boleh.
        $this->actingAs($staff)->post(route('admin.orders.ship', $second->id), ['courier' => 'SiCepat', 'tracking_number' => 'JNE0001234'])
            ->assertSessionHasNoErrors();
    }

    public function test_gudang_can_ship_from_the_queue_and_sees_customer_choice_prefilled(): void
    {
        $o = $this->order('diproses', 'JNE REG');
        $gudang = $this->as('gudang');

        $this->actingAs($gudang)->get(route('admin.shipments.index'))
            ->assertOk()->assertSee('JNE REG')->assertSee('estimasi 2-3 hari')->assertSee('name="tracking_number"', false)
            ->assertSee('<option value="JNE" selected', false);

        $this->actingAs($gudang)->post(route('admin.orders.ship', $o->id), ['courier' => 'JNE', 'tracking_number' => 'GDG123456'])
            ->assertSessionHasNoErrors();
        $this->assertSame('dikirim', $o->fresh()->status);
    }

    public function test_order_detail_shows_form_with_customer_choice(): void
    {
        $o = $this->order('dikonfirmasi', 'SiCepat Halu');

        $this->actingAs($this->as('staff'))->get(route('admin.orders.show', $o->id))
            ->assertOk()->assertSee('Pelanggan memilih')->assertSee('SiCepat Halu')->assertSee('Lainnya…');
    }

    public function test_customer_sees_courier_resi_and_copy_button_after_shipping(): void
    {
        $customerUser = User::factory()->create();
        $customerUser->assignRole('customer');
        $o = $this->order('dikirim', 'JNE REG', $customerUser);
        Shipment::create(['order_id' => $o->id, 'courier' => 'JNE', 'service' => 'REG', 'tracking_number' => 'CGK999888', 'status' => 'shipped', 'shipped_at' => now()]);

        $this->actingAs($customerUser)->get(route('customer.orders.show', $o->order_number))
            ->assertOk()->assertSee('CGK999888')->assertSee('JNE')->assertSee('(REG)')->assertSee('Salin resi')->assertSee('Lacak paket');
    }

    public function test_correcting_resi_on_shipment_list_also_checks_duplicates(): void
    {
        $a = $this->order('dikirim');
        $b = $this->order('dikirim');
        Shipment::create(['order_id' => $a->id, 'courier' => 'JNE', 'tracking_number' => 'AAA11111', 'status' => 'shipped']);
        $sb = Shipment::create(['order_id' => $b->id, 'courier' => 'JNE', 'tracking_number' => 'BBB22222', 'status' => 'shipped']);

        $this->actingAs($this->as('staff'))->patch(route('admin.shipments.update', $sb->id), ['courier' => 'JNE', 'tracking_number' => 'aaa11111'])
            ->assertSessionHasErrors('tracking_number');
        $this->assertSame('BBB22222', $sb->fresh()->tracking_number);
    }
}
