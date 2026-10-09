<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function product(int $stock = 10): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create([
            'name' => "Mesin {$n}", 'slug' => "mesin-{$n}", 'sku' => "SKU-{$n}",
            'price' => 1000, 'stock' => $stock, 'stock_threshold' => 5,
        ]));
    }

    // ---------------- STOK ----------------

    public function test_stock_in_out_and_opname_are_recorded(): void
    {
        $staff = $this->userWithRole('staff');
        $p = $this->product(10);

        $this->actingAs($staff)->post(route('admin.stock.adjust'), ['product_id' => $p->id, 'type' => 'in', 'quantity' => 5])->assertSessionHas('success');
        $this->assertSame(15, $p->fresh()->stock);

        $this->actingAs($staff)->post(route('admin.stock.adjust'), ['product_id' => $p->id, 'type' => 'out', 'quantity' => 3, 'note' => 'Unit display rusak'])->assertSessionHas('success');
        $this->assertSame(12, $p->fresh()->stock);

        $this->actingAs($staff)->post(route('admin.stock.adjust'), ['product_id' => $p->id, 'type' => 'set', 'quantity' => 9, 'note' => 'Hitung fisik'])->assertSessionHas('success');
        $this->assertSame(9, $p->fresh()->stock);

        $this->assertSame(3, StockMovement::where('product_id', $p->id)->count());
        $opname = StockMovement::where('type', 'adjustment')->first();
        $this->assertSame(-3, $opname->quantity); // 12 -> 9
        $this->assertSame($staff->id, $opname->user_id);
    }

    public function test_stock_cannot_go_negative_and_reasons_are_required(): void
    {
        $staff = $this->userWithRole('staff');
        $p = $this->product(2);

        $this->actingAs($staff)->post(route('admin.stock.adjust'), ['product_id' => $p->id, 'type' => 'out', 'quantity' => 5, 'note' => 'x'])->assertSessionHas('error');
        $this->actingAs($staff)->post(route('admin.stock.adjust'), ['product_id' => $p->id, 'type' => 'out', 'quantity' => 1])->assertSessionHas('error'); // tanpa alasan
        $this->actingAs($staff)->post(route('admin.stock.adjust'), ['product_id' => $p->id, 'type' => 'set', 'quantity' => 2, 'note' => 'sama'])->assertSessionHas('error'); // tidak berubah

        $this->assertSame(2, $p->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_stock_pages_render_and_low_stock_filter_works(): void
    {
        $staff = $this->userWithRole('staff');
        $low = $this->product(3);
        $ok = $this->product(50);
        $out = $this->product(0);

        $this->actingAs($staff)->get(route('admin.stock.index'))->assertOk()->assertSee($low->name);
        $res = $this->actingAs($staff)->get(route('admin.stock.index', ['status' => 'low']))->assertOk();
        $res->assertSee($low->name)->assertDontSee($ok->name)->assertDontSee($out->name);
        $this->actingAs($staff)->get(route('admin.stock.history'))->assertOk();
    }

    public function test_product_edit_cannot_change_stock_directly(): void
    {
        $admin = $this->userWithRole('admin');
        $cat = Category::unguarded(fn () => Category::create(['name' => 'Espresso', 'slug' => 'espresso']));
        $brand = Brand::unguarded(fn () => Brand::create(['name' => 'Nuova', 'slug' => 'nuova']));
        $p = $this->product(10);

        $this->actingAs($admin)->put(route('admin.products.update', $p->id), [
            'category_id' => $cat->id, 'brand_id' => $brand->id, 'name' => 'Mesin Baru', 'description' => 'x',
            'price' => 2000, 'stock' => 999, 'stock_threshold' => 5,
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Mesin Baru', $p->name);
        $this->assertSame(10, $p->stock); // stok 999 dari form diabaikan
    }

    // ---------------- PENGIRIMAN ----------------

    public function test_shipments_queue_and_tracking_correction(): void
    {
        $staff = $this->userWithRole('staff');
        $user = User::factory()->create();
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => 'Budi', 'email' => $user->email]));
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id,
            'status' => 'dikonfirmasi', 'subtotal' => 1, 'total' => 1,
        ]));

        $this->actingAs($staff)->get(route('admin.shipments.index'))->assertOk()->assertSee($order->order_number);

        app(OrderService::class)->ship($order, ['courier' => 'JNE', 'tracking_number' => 'SALAH1']);

        $this->actingAs($staff)->get(route('admin.shipments.index', ['tab' => 'shipped']))->assertOk()->assertSee('SALAH1');

        $shipment = Shipment::first();
        $this->actingAs($staff)->patch(route('admin.shipments.update', $shipment->id), ['courier' => 'JNE', 'tracking_number' => 'BENAR2'])->assertSessionHas('success');
        $this->assertSame('BENAR2', $shipment->fresh()->tracking_number);
    }

    // ---------------- USER ----------------

    public function test_super_admin_can_create_user_with_role_and_hashed_password(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)->post(route('admin.users.store'), [
            'name' => 'Sari Staff', 'email' => 'sari@example.com', 'role' => 'staff',
            'password' => 'rahasia-banget-1', 'password_confirmation' => 'rahasia-banget-1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $u = User::where('email', 'sari@example.com')->first();
        $this->assertTrue($u->hasRole('staff'));
        $this->assertNotSame('rahasia-banget-1', $u->password);
        $this->assertTrue(\Hash::check('rahasia-banget-1', $u->password));

        $this->actingAs($super)->get(route('admin.users.index'))->assertOk()->assertSee('sari@example.com');
    }

    public function test_users_menu_is_super_admin_only(): void
    {
        foreach (['admin', 'staff', 'customer'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('admin.users.index'))->assertForbidden();
        }
    }

    public function test_cannot_delete_or_demote_yourself_or_the_last_super_admin(): void
    {
        $super = $this->userWithRole('super_admin');

        $this->actingAs($super)->delete(route('admin.users.destroy', $super->id))->assertSessionHas('error');
        $this->assertNotNull($super->fresh());

        $this->actingAs($super)->put(route('admin.users.update', $super->id), [
            'name' => $super->name, 'email' => $super->email, 'role' => 'staff',
        ])->assertSessionHas('error');
        $this->assertTrue($super->fresh()->hasRole('super_admin'));

        // super admin kedua mencoba menurunkan super admin satu-satunya yang tersisa -> harus ditolak
        $second = $this->userWithRole('super_admin');
        $this->actingAs($second)->put(route('admin.users.update', $super->id), [
            'name' => $super->name, 'email' => $super->email, 'role' => 'staff',
        ])->assertSessionHasNoErrors(); // ada 2 super admin -> boleh
        $this->assertTrue($super->fresh()->hasRole('staff'));

        // sekarang tinggal $second; ia tak bisa menghapus/menurunkan dirinya (juga satu-satunya)
        $this->actingAs($second)->delete(route('admin.users.destroy', $second->id))->assertSessionHas('error');
    }

    public function test_deleting_a_user_is_a_soft_delete(): void
    {
        $super = $this->userWithRole('super_admin');
        $target = $this->userWithRole('staff');

        $this->actingAs($super)->delete(route('admin.users.destroy', $target->id))->assertSessionHas('success');
        $this->assertSoftDeleted($target);
    }

    // ---------------- ROLE / MATRIKS ----------------

    public function test_role_matrix_changes_take_effect_immediately(): void
    {
        $super = $this->userWithRole('super_admin');
        $staff = $this->userWithRole('staff');
        $staffRole = Role::findByName('staff');

        $this->actingAs($staff)->get(route('admin.brands.create'))->assertForbidden();

        $current = $staffRole->permissions->pluck('name')->all();
        $this->actingAs($super)->put(route('admin.roles.update', $staffRole->id), [
            'permissions' => array_merge($current, ['create_brands']),
        ])->assertSessionHas('success');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($staff->fresh())->get(route('admin.brands.create'))->assertOk();

        // dicabut lagi -> kembali 403
        $this->actingAs($super)->put(route('admin.roles.update', $staffRole->id), ['permissions' => $current]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($staff->fresh())->get(route('admin.brands.create'))->assertForbidden();
    }

    public function test_admin_only_permissions_cannot_be_granted_to_other_roles_and_locked_roles_are_protected(): void
    {
        $super = $this->userWithRole('super_admin');
        $staffRole = Role::findByName('staff');

        $this->actingAs($super)->put(route('admin.roles.update', $staffRole->id), [
            'permissions' => ['view_orders', 'view_users', 'manage_roles', 'view_activity_logs'],
        ]);
        $this->assertTrue($staffRole->fresh()->hasPermissionTo('view_orders'));
        $this->assertFalse($staffRole->fresh()->hasPermissionTo('manage_roles'));
        $this->assertFalse($staffRole->fresh()->hasPermissionTo('view_users'));

        $superRole = Role::findByName('super_admin');
        $this->actingAs($super)->put(route('admin.roles.update', $superRole->id), ['permissions' => []])->assertSessionHas('error');
        $this->assertGreaterThan(0, $superRole->fresh()->permissions->count());

        $this->actingAs($super)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($super)->get(route('admin.roles.edit', $staffRole->id))->assertOk();
    }

    // ---------------- ACTIVITY LOG ----------------

    public function test_order_status_changes_are_logged_with_the_actor(): void
    {
        $staff = $this->userWithRole('staff');
        $user = User::factory()->create();
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => 'B', 'email' => $user->email]));
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id,
            'status' => 'dikonfirmasi', 'subtotal' => 1, 'total' => 1,
        ]));

        $this->actingAs($staff);
        app(OrderService::class)->process($order);

        $log = Activity::where('log_name', 'pesanan')->where('event', 'updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($staff->id, $log->causer_id);
        $this->assertSame('diproses', $log->attribute_changes['attributes']['status']);
        $this->assertSame('dikonfirmasi', $log->attribute_changes['old']['status']);
    }

    public function test_user_password_is_never_written_to_the_log(): void
    {
        $super = $this->userWithRole('super_admin');
        $this->actingAs($super)->post(route('admin.users.store'), [
            'name' => 'Rahasia', 'email' => 'r@example.com', 'role' => 'staff',
            'password' => 'password-rahasia-1', 'password_confirmation' => 'password-rahasia-1',
        ]);

        $dump = Activity::all()->toJson();
        $this->assertStringNotContainsString('password-rahasia-1', $dump);
        $this->assertStringNotContainsString('"password"', $dump);
    }

    public function test_log_viewer_is_super_admin_only_and_filters_work(): void
    {
        $super = $this->userWithRole('super_admin');
        $p = $this->product(10);
        $this->actingAs($super);
        $p->update(['price' => 5555]);

        $this->get(route('admin.logs.index'))->assertOk()->assertSee('Produk updated')->assertSee('5555');
        $this->get(route('admin.logs.index', ['log' => 'produk']))->assertOk()->assertSee('Produk updated');
        $this->get(route('admin.logs.index', ['log' => 'pesanan']))->assertOk()->assertDontSee('Produk updated');

        foreach (['admin', 'staff'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('admin.logs.index'))->assertForbidden();
        }
    }

    public function test_sidebar_hides_menus_the_user_cannot_open(): void
    {
        $staff = $this->userWithRole('staff');
        $res = $this->actingAs($staff)->get(route('admin.orders.index'))->assertOk();
        $res->assertSee('Pesanan')->assertDontSee('Activity Log')->assertDontSee('Roles');

        $super = $this->userWithRole('super_admin');
        $this->actingAs($super)->get(route('admin.orders.index'))->assertSee('Activity Log');
    }
}
