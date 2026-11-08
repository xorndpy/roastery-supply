<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Menjaga batas hak akses antar role (gudang tidak boleh menutup pesanan / melihat uang, dst). */
class RoleAccessTest extends TestCase
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

    private function order(string $status): Order
    {
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '0811']);

        return Order::unguarded(fn () => Order::create([
            'order_number' => 'ORD-T-' . uniqid(), 'customer_id' => $customer->id, 'status' => $status,
            'subtotal' => 777000, 'total' => 777000,
        ]));
    }

    public function test_gudang_can_process_but_cannot_complete_or_confirm_payment(): void
    {
        $gudang = $this->as('gudang');

        $this->assertTrue($gudang->can('process_orders'));
        $this->assertTrue($gudang->can('manage_shipments'));
        $this->assertFalse($gudang->can('complete_orders'));
        $this->assertFalse($gudang->can('verify_payments'));
        $this->assertFalse($gudang->can('view_finance'));

        $shipped = $this->order('dikirim');
        $this->actingAs($gudang)->post(route('admin.orders.complete', $shipped->id))->assertForbidden();
        $this->assertSame('dikirim', $shipped->fresh()->status);
    }

    public function test_staff_and_admin_can_complete_orders(): void
    {
        foreach (['staff', 'admin', 'super_admin'] as $role) {
            $this->assertTrue($this->as($role)->can('complete_orders'), "{$role} harus bisa menyelesaikan pesanan");
            $this->assertTrue($this->as($role)->can('process_orders'));
        }
    }

    public function test_order_buttons_are_hidden_for_gudang_but_visible_for_staff(): void
    {
        $confirmed = $this->order('dikonfirmasi');
        $shipped = $this->order('dikirim');

        $g = $this->as('gudang');
        $this->actingAs($g)->get(route('admin.orders.show', $confirmed->id))
            ->assertOk()->assertSee('Mulai Proses')->assertDontSee('Konfirmasi Pembayaran');
        $this->actingAs($g)->get(route('admin.orders.show', $shipped->id))
            ->assertOk()->assertDontSee('Tandai Selesai');

        $s = $this->as('staff');
        $this->actingAs($s)->get(route('admin.orders.show', $shipped->id))->assertOk()->assertSee('Tandai Selesai');
    }

    public function test_dashboard_hides_money_without_view_finance(): void
    {
        $this->order('selesai');

        // Staff yang hak view_finance-nya dicabut (mis. dari matriks role) tidak boleh melihat uang.
        Role::findByName('staff')->revokePermissionTo('view_finance');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $cs = $this->as('staff');

        $this->actingAs($cs)->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee('Pendapatan 7 hari terakhir')->assertDontSee('Total keseluruhan')->assertDontSee('777.000');

        $this->actingAs($this->as('admin'))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Pendapatan 7 hari terakhir')->assertSee('777.000');
    }

    public function test_only_super_admin_manages_users_roles_and_logs(): void
    {
        foreach (['view_users', 'create_users', 'manage_roles', 'view_activity_logs'] as $perm) {
            $this->assertFalse($this->as('admin')->can($perm), "admin tidak boleh punya {$perm}");
            $this->assertFalse($this->as('staff')->can($perm));
            $this->assertFalse($this->as('gudang')->can($perm));
            $this->assertTrue($this->as('super_admin')->can($perm));
        }
    }

    public function test_staff_setup_command_creates_four_accounts_with_random_passwords(): void
    {
        $this->artisan('staff:setup', ['--domain' => 'toko.test', '--super-email' => 'owner@toko.test'])->assertSuccessful();

        $expected = ['owner@toko.test' => 'super_admin', 'admin@toko.test' => 'admin', 'staff@toko.test' => 'staff', 'gudang@toko.test' => 'gudang'];
        foreach ($expected as $email => $role) {
            $u = User::where('email', $email)->first();
            $this->assertNotNull($u, "{$email} harus dibuat");
            $this->assertTrue($u->hasRole($role));
            $this->assertNotNull($u->email_verified_at);
            $this->assertNotSame('password', $u->password);
        }

        // Dijalankan ulang: tidak mengubah password akun yang sudah ada.
        $before = User::where('email', 'admin@toko.test')->value('password');
        $this->artisan('staff:setup', ['--domain' => 'toko.test', '--super-email' => 'owner@toko.test'])->assertSuccessful();
        $this->assertSame($before, User::where('email', 'admin@toko.test')->value('password'));
        $this->assertSame(4, User::count());
    }

    public function test_migration_moves_existing_edit_orders_roles_and_strips_gudang(): void
    {
        // Simulasi database lama: gudang masih punya edit_orders, role kustom punya edit_orders.
        $gudang = Role::findByName('gudang');
        $gudang->givePermissionTo('edit_orders');
        $gudang->revokePermissionTo(['process_orders']);
        $custom = Role::create(['name' => 'kasir', 'guard_name' => 'web']);
        $custom->givePermissionTo('edit_orders');

        $migration = require database_path('migrations/2026_11_09_000001_split_order_permissions.php');
        $migration->up();
        $migration->up(); // idempoten

        $gudang = Role::findByName('gudang')->refresh();
        $this->assertTrue($gudang->hasPermissionTo('process_orders'));
        $this->assertFalse($gudang->hasPermissionTo('edit_orders'));
        $this->assertFalse($gudang->hasPermissionTo('complete_orders'));

        $custom = Role::findByName('kasir')->refresh();
        $this->assertTrue($custom->hasPermissionTo('process_orders'));
        $this->assertTrue($custom->hasPermissionTo('complete_orders'));
    }
}
