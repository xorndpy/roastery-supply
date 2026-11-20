<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTampilanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function as(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function order(string $status, ?string $createdAtUtc = null, float $total = 777000): Order
    {
        static $n = 0;
        $customer = Customer::create(['name' => 'Budi', 'email' => 'budi' . (++$n) . '@example.com', 'phone' => '0811']);
        $attrs = [
            'order_number' => 'ORD-D-' . $n, 'customer_id' => $customer->id, 'status' => $status,
            'subtotal' => $total, 'total' => $total,
        ];
        if ($createdAtUtc) {
            $attrs['created_at'] = $createdAtUtc;
            $attrs['updated_at'] = $createdAtUtc;
        }

        return Order::unguarded(fn () => Order::create($attrs));
    }

    public function test_dashboard_memuat_bagian_analitik_dan_kalender(): void
    {
        $this->order('selesai');

        $this->actingAs($this->as('admin'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Penjualan per kategori')
            ->assertSee('Rata-rata nilai pesanan')
            ->assertSee('Kalender')
            ->assertSee('Status pesanan')
            ->assertSee('Pelanggan baru');
    }

    public function test_grafik_memakai_hari_wib_bukan_utc(): void
    {
        // 9 Nov 2026 12:00 WIB. Pesanan 8 Nov 20:00 UTC = 9 Nov 03:00 WIB => masuk hari ini (WIB).
        Carbon::setTestNow('2026-11-09 05:00:00');
        $this->order('selesai', '2026-11-08 20:00:00');

        $html = $this->actingAs($this->as('admin'))->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-today="2026-11-09"', $html);
        $this->assertStringContainsString("data-ord='[0,0,0,0,0,0,1]'", $html);
    }

    public function test_rentang_90_hari_didukung(): void
    {
        $this->actingAs($this->as('admin'))->get(route('admin.dashboard', ['range' => 90]))
            ->assertOk()->assertSee('Pendapatan 90 hari terakhir');
    }

    public function test_kalender_mengelompokkan_pesanan_per_tanggal_wib(): void
    {
        $this->order('selesai', '2026-11-08 20:00:00');   // 9 Nov 03:00 WIB
        $this->order('selesai', '2026-10-31 20:00:00');   // 1 Nov 03:00 WIB (masuk November, bukan Oktober)
        $admin = $this->as('admin');

        $nov = $this->actingAs($admin)->getJson(route('admin.dashboard.calendar', ['month' => '2026-11']))->assertOk()->json();
        $this->assertSame('2026-11', $nov['month']);
        $this->assertSame(['2026-11-01', '2026-11-09'], array_keys($nov['days']));
        $this->assertSame('03:00', $nov['days']['2026-11-09'][0]['h']);
        $this->assertArrayHasKey('t', $nov['days']['2026-11-09'][0]);

        $oct = $this->getJson(route('admin.dashboard.calendar', ['month' => '2026-10']))->assertOk()->json();
        $this->assertSame([], $oct['days']);
    }

    public function test_kalender_menolak_bulan_tidak_valid(): void
    {
        $this->actingAs($this->as('admin'))->getJson(route('admin.dashboard.calendar', ['month' => '2026-13']))->assertStatus(422);
        $this->getJson(route('admin.dashboard.calendar', ['month' => 'abc']))->assertStatus(422);
    }

    public function test_kalender_tertutup_untuk_customer(): void
    {
        $this->actingAs($this->as('customer'))->getJson(route('admin.dashboard.calendar'))->assertForbidden();
    }

    public function test_tanpa_hak_finance_nominal_tidak_bocor_di_dashboard_maupun_kalender(): void
    {
        $this->order('selesai');
        Role::findByName('staff')->revokePermissionTo('view_finance');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $staff = $this->as('staff');

        $this->actingAs($staff)->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee('777.000')->assertDontSee('Rata-rata nilai pesanan')->assertDontSee('data-rev');

        $json = $this->getJson(route('admin.dashboard.calendar'))->assertOk()->json();
        foreach ($json['days'] as $orders) {
            foreach ($orders as $o) {
                $this->assertArrayNotHasKey('t', $o);
            }
        }
    }
}
