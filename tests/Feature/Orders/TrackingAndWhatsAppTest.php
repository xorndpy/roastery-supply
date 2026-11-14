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
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warranty;
use App\Notifications\OrderStatusChanged;
use App\Services\OrderService;
use App\Services\TrackingService;
use App\Services\WhatsAppService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Pelacakan resi otomatis (Komerce/RajaOngkir), timeline pesanan, dan notifikasi WhatsApp (Fonnte). */
class TrackingAndWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private const TRACK = 'rajaongkir.komerce.id/*';
    private const WA = 'api.fonnte.com/*';

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forgetMemo();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function enableTracking(): void
    {
        config(['services.rajaongkir.key' => 'test-key']);
    }

    private function enableWhatsApp(): void
    {
        config(['services.whatsapp.token' => 'wa-token']);
        Setting::set('wa_enabled', '1');
        Setting::set('wa_store_number', '081300000000');
    }

    private function product(): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Mesin T{$n}", 'slug' => "mesin-t{$n}", 'sku' => "T-{$n}", 'price' => 2000000, 'stock' => 20, 'stock_threshold' => 1, 'is_active' => true]));
    }

    private function shipped(string $courier = 'JNE', string $resi = 'JNE0012345', string $status = 'dikirim'): Order
    {
        $u = User::factory()->create(['name' => 'Budi']);
        $u->assignRole('customer');
        $c = Customer::unguarded(fn () => Customer::create(['user_id' => $u->id, 'name' => $u->name, 'email' => $u->email]));
        $p = $this->product();
        $o = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $c->id, 'user_id' => $u->id, 'status' => $status,
            'subtotal' => 2000000, 'discount' => 0, 'shipping_cost' => 0, 'total' => 2000000, 'shipping_name' => 'Budi', 'shipping_phone' => '081234567890',
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => 2000000, 'quantity' => 1, 'subtotal' => 2000000]));
        Shipment::unguarded(fn () => Shipment::create(['order_id' => $o->id, 'courier' => $courier, 'tracking_number' => $resi, 'status' => 'shipped', 'shipped_at' => now()]));

        return $o->fresh(['shipment']);
    }

    private function manifestResponse(bool $delivered): array
    {
        return ['meta' => ['code' => 200, 'status' => 'success'], 'data' => [
            'delivered' => $delivered,
            'summary' => ['courier_code' => 'jne', 'waybill_number' => 'JNE0012345', 'status' => $delivered ? 'DELIVERED' : 'ON PROCESS'],
            'manifest' => [
                ['manifest_code' => '1', 'manifest_description' => 'Paket diterima di gudang Jakarta', 'manifest_date' => '2026-11-12', 'manifest_time' => '08:00', 'city_name' => 'JAKARTA'],
                ['manifest_code' => '2', 'manifest_description' => 'Paket dibawa kurir', 'manifest_date' => '2026-11-13', 'manifest_time' => '09:30', 'city_name' => 'BANDUNG'],
            ] + ($delivered ? [2 => ['manifest_code' => '3', 'manifest_description' => 'Diterima oleh BUDI', 'manifest_date' => '2026-11-13', 'manifest_time' => '14:00', 'city_name' => 'BANDUNG']] : []),
        ]];
    }

    // ================= pelacakan =================

    public function test_courier_names_map_to_rajaongkir_codes(): void
    {
        foreach (['JNE' => 'jne', 'J&T' => 'jnt', 'SiCepat' => 'sicepat', 'AnterAja' => 'anteraja', 'Ninja Xpress' => 'ninja', 'ID Express' => 'ide', 'POS Indonesia' => 'pos', 'Lion Parcel' => 'lion', 'SAP Express' => 'sap'] as $name => $code) {
            $this->assertSame($code, TrackingService::courierCode($name), $name);
        }
        $this->assertNull(TrackingService::courierCode('Kurir Toko (antar sendiri)'));
        $this->assertNull(TrackingService::courierCode(null));
    }

    public function test_sync_stores_the_courier_timeline_newest_first_and_keeps_the_order_shipped(): void
    {
        $this->enableTracking();
        Http::fake([self::TRACK => Http::response($this->manifestResponse(false))]);
        $o = $this->shipped();

        $this->assertTrue(app(TrackingService::class)->sync($o->shipment));

        $s = $o->shipment->fresh();
        $this->assertSame('ON PROCESS', $s->tracking_status);
        $this->assertSame('Paket dibawa kurir', $s->tracking_events[0]['desc']);        // terbaru di atas
        $this->assertSame('BANDUNG', $s->tracking_events[0]['city']);
        $this->assertCount(2, $s->tracking_events);
        $this->assertNotNull($s->tracking_checked_at);
        $this->assertSame('dikirim', $o->fresh()->status);

        Http::assertSent(function (HttpRequest $r) {
            return $r->hasHeader('key', 'test-key') && str_contains($r->url(), 'awb=JNE0012345') && str_contains($r->url(), 'courier=jne') && str_contains($r->url(), 'last_phone_number=67890');
        });
    }

    public function test_delivered_package_completes_the_order_notifies_and_issues_warranty(): void
    {
        $this->enableTracking();
        Notification::fake();
        Http::fake([self::TRACK => Http::response($this->manifestResponse(true))]);
        $o = $this->shipped();

        app(TrackingService::class)->sync($o->shipment);

        $this->assertSame('selesai', $o->fresh()->status);
        $this->assertNotNull($o->fresh()->completed_at);
        $this->assertSame('delivered', $o->shipment->fresh()->status);
        $this->assertSame(1, Warranty::where('order_id', $o->id)->count());
        Notification::assertSentTo($o->user, OrderStatusChanged::class, fn ($n) => $n->event === 'selesai');
    }

    public function test_sync_is_safe_without_key_unsupported_courier_or_api_errors(): void
    {
        Http::fake();
        $svc = app(TrackingService::class);

        // tanpa API key
        $o = $this->shipped();
        $this->assertFalse($svc->sync($o->shipment));
        Http::assertNothingSent();

        $this->enableTracking();
        // kurir toko tidak didukung
        $own = $this->shipped('Kurir Toko (antar sendiri)', 'XYZ123');
        $this->assertFalse($svc->sync($own->shipment));
        Http::assertNothingSent();

        // API error: pesanan tidak berubah, waktu cek tetap dicatat agar tidak menembak terus-menerus
        Http::fake([self::TRACK => Http::response(['meta' => ['code' => 500]], 500)]);
        $this->assertFalse($svc->sync($o->shipment));
        $this->assertSame('dikirim', $o->fresh()->status);
        $this->assertNotNull($o->shipment->fresh()->tracking_checked_at);

        // koneksi putus
        Http::fake([self::TRACK => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->assertFalse($svc->sync($o->shipment));
        $this->assertSame('dikirim', $o->fresh()->status);
    }

    public function test_artisan_command_only_checks_shipped_stale_orders_and_respects_the_limit(): void
    {
        $this->enableTracking();
        Http::fake([self::TRACK => Http::response($this->manifestResponse(false))]);
        $a = $this->shipped('JNE', 'AAA111');
        $b = $this->shipped('JNE', 'BBB222');
        $done = $this->shipped('JNE', 'CCC333', 'selesai');                       // bukan dikirim
        $fresh = $this->shipped('JNE', 'DDD444');
        $fresh->shipment->forceFill(['tracking_checked_at' => now()->subMinutes(5)])->save(); // baru dicek

        $this->artisan('tracking:sync --limit=1')->assertSuccessful();
        Http::assertSentCount(1);

        $this->artisan('tracking:sync')->assertSuccessful();
        $this->assertNotNull($a->shipment->fresh()->tracking_checked_at);
        $this->assertNotNull($b->shipment->fresh()->tracking_checked_at);
        $this->assertNull($done->shipment->fresh()->tracking_checked_at);
        $this->assertTrue($fresh->shipment->fresh()->tracking_checked_at->gt(now()->subMinutes(10)));
    }

    public function test_command_is_a_no_op_without_an_api_key_and_is_scheduled(): void
    {
        Http::fake();
        $this->shipped();
        $this->artisan('tracking:sync')->expectsOutputToContain('nonaktif')->assertSuccessful();
        Http::assertNothingSent();

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');
        $this->assertStringContainsString('tracking:sync', $events);
    }

    public function test_admin_can_trigger_a_manual_check_and_only_with_permission(): void
    {
        $this->enableTracking();
        Http::fake([self::TRACK => Http::response($this->manifestResponse(false))]);
        $o = $this->shipped();
        $staff = User::factory()->create(); $staff->assignRole('staff');
        $keu = User::factory()->create(); $keu->assignRole('keuangan');

        $this->actingAs($keu)->post(route('admin.orders.track', $o->id))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.orders.track', $o->id))->assertSessionHas('success');
        $this->assertCount(2, $o->shipment->fresh()->tracking_events);

        $none = $this->shipped('Kurir Toko (antar sendiri)', 'X1');
        $this->actingAs($staff)->post(route('admin.orders.track', $none->id))->assertSessionHas('error');
    }

    public function test_timeline_shows_internal_steps_and_courier_events(): void
    {
        $this->enableTracking();
        Http::fake([self::TRACK => Http::response($this->manifestResponse(false))]);
        $o = $this->shipped();
        Payment::unguarded(fn () => Payment::create(['order_id' => $o->id, 'method' => 'manual', 'amount' => 2000000, 'status' => 'verified', 'verified_at' => now()]));
        app(TrackingService::class)->sync($o->shipment);

        $this->actingAs($o->user)->get(route('customer.orders.show', $o->order_number))
            ->assertOk()->assertSee('Timeline pesanan')->assertSee('Pesanan dibuat')->assertSee('Pembayaran dikonfirmasi')
            ->assertSee('Dikirim')->assertSee('Paket dibawa kurir')->assertSee('Paket diterima di gudang Jakarta');

        $admin = User::factory()->create(); $admin->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.orders.show', $o->id))->assertOk()->assertSee('Timeline pesanan')->assertSee('Cek resi sekarang');
    }

    public function test_timeline_still_works_without_courier_data(): void
    {
        $o = $this->shipped();

        $this->actingAs($o->user)->get(route('customer.orders.show', $o->order_number))
            ->assertOk()->assertSee('Timeline pesanan')->assertSee('Perjalanan paket akan muncul di sini');
    }

    // ================= WhatsApp =================

    public function test_phone_numbers_are_normalized(): void
    {
        foreach (['0812-3456-7890' => '6281234567890', '+62 812 3456 7890' => '6281234567890', '81234567890' => '6281234567890', '6281234567890' => '6281234567890'] as $in => $out) {
            $this->assertSame($out, WhatsAppService::normalize($in), $in);
        }
        foreach ([null, '', 'abc', '0812', '12345678901234567890', '+1 202 555 0100'] as $bad) {
            $this->assertNull(WhatsAppService::normalize($bad), (string) $bad);
        }
    }

    public function test_whatsapp_is_off_without_token_or_the_setting(): void
    {
        Http::fake();
        $wa = app(WhatsAppService::class);

        $this->assertFalse($wa->send('081234567890', 'hi'));                      // tanpa token & setting

        config(['services.whatsapp.token' => 'wa-token']);
        $this->assertFalse($wa->send('081234567890', 'hi'));                      // token ada, setting nonaktif

        Setting::set('wa_enabled', '1');
        config(['services.whatsapp.token' => '']);
        $this->assertFalse($wa->send('081234567890', 'hi'));                      // setting aktif, token kosong
        Http::assertNothingSent();
    }

    public function test_whatsapp_sends_the_documented_fonnte_request(): void
    {
        $this->enableWhatsApp();
        Http::fake([self::WA => Http::response(['status' => true, 'id' => ['1']])]);

        $this->assertTrue(app(WhatsAppService::class)->send('0812-3456-7890', 'Halo dunia'));

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send' && $r->hasHeader('Authorization', 'wa-token')
            && $r['target'] === '6281234567890' && $r['message'] === 'Halo dunia' && $r['countryCode'] === '62');
    }

    public function test_whatsapp_failures_never_throw(): void
    {
        $this->enableWhatsApp();
        $wa = app(WhatsAppService::class);

        Http::fake([self::WA => Http::response(['status' => false, 'reason' => 'token invalid'], 200)]);
        $this->assertFalse($wa->send('081234567890', 'x'));

        Http::fake([self::WA => Http::response('error', 500)]);
        $this->assertFalse($wa->send('081234567890', 'x'));

        Http::fake([self::WA => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->assertFalse($wa->send('081234567890', 'x'));

        $this->assertFalse($wa->send('nomor-salah', 'x'));
    }

    public function test_new_order_messages_the_store_and_the_customer(): void
    {
        $this->enableWhatsApp();
        Http::fake([self::WA => Http::response(['status' => true])]);
        Notification::fake();
        $prov = Province::firstOrCreate(['code' => '32'], ['name' => 'Jawa Barat']);
        $city = City::firstOrCreate(['code' => '32.73'], ['province_id' => $prov->id, 'name' => 'Kota Bandung']);
        $u = User::factory()->create(); $u->assignRole('customer');
        $p = $this->product();

        $this->actingAs($u)->withSession(['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => 1, 'weight' => 1000, 'image' => null, 'stock' => 20]], 'checkout_key' => 'k1'])
            ->post(route('customer.checkout.store'), ['idempotency_key' => 'k1', 'name' => 'Budi', 'phone' => '081234567890', 'address' => 'Jl. Kopi 1', 'province_id' => $prov->id, 'city_id' => $city->id, 'postal_code' => '40111'])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (HttpRequest $r) => $r['target'] === '6281300000000' && str_contains($r['message'], 'Pesanan baru ORD-'));
        Http::assertSent(fn (HttpRequest $r) => $r['target'] === '6281234567890' && str_contains($r['message'], 'sudah kami terima'));
        Http::assertSentCount(2);
    }

    public function test_a_whatsapp_outage_never_breaks_checkout(): void
    {
        $this->enableWhatsApp();
        Http::fake([self::WA => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        Notification::fake();
        $prov = Province::firstOrCreate(['code' => '32'], ['name' => 'Jawa Barat']);
        $city = City::firstOrCreate(['code' => '32.73'], ['province_id' => $prov->id, 'name' => 'Kota Bandung']);
        $u = User::factory()->create(); $u->assignRole('customer');
        $p = $this->product();

        $this->actingAs($u)->withSession(['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => 1, 'weight' => 1000, 'image' => null, 'stock' => 20]], 'checkout_key' => 'k1'])
            ->post(route('customer.checkout.store'), ['idempotency_key' => 'k1', 'name' => 'Budi', 'phone' => '081234567890', 'address' => 'Jl. Kopi 1', 'province_id' => $prov->id, 'city_id' => $city->id, 'postal_code' => '40111'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count());
    }

    public function test_shipping_messages_the_customer_with_the_tracking_number(): void
    {
        $this->enableWhatsApp();
        Notification::fake();
        Http::fake([self::WA => Http::response(['status' => true])]);
        $o = $this->shipped('JNE', 'X', 'diproses');
        $o->shipment()->delete();

        app(OrderService::class)->ship($o, ['courier' => 'JNE', 'tracking_number' => 'JNE9988776655']);

        Http::assertSent(fn (HttpRequest $r) => $r['target'] === '6281234567890' && str_contains($r['message'], 'JNE9988776655') && str_contains($r['message'], 'sudah dikirim'));
    }

    public function test_confirmed_payment_messages_the_store(): void
    {
        $this->enableWhatsApp();
        Notification::fake();
        Http::fake([self::WA => Http::response(['status' => true])]);
        $o = $this->shipped('JNE', 'Y', 'menunggu_bayar');
        Payment::unguarded(fn () => Payment::create(['order_id' => $o->id, 'method' => 'manual', 'amount' => 2000000, 'status' => 'pending']));

        app(OrderService::class)->confirmPayment($o, User::factory()->create()->id);

        Http::assertSent(fn (HttpRequest $r) => $r['target'] === '6281300000000' && str_contains($r['message'], 'Pembayaran masuk'));
    }
}
