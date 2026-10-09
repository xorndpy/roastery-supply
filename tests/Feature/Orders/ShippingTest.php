<?php

namespace Tests\Feature\Orders;

use App\Models\City;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Province;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\Voucher;
use App\Services\ShippingService;
use App\Support\Regions;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShippingDemoSeeder;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesRegions;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase, MakesRegions;

    private ShippingService $shipping;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::forgetMemo();
        Cache::flush();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $this->shipping = app(ShippingService::class);
    }

    // ---------- helpers ----------

    private function method(string $name = 'Kargo', bool $active = true): ShippingMethod
    {
        return ShippingMethod::create(['name' => $name, 'sort_order' => 1, 'is_active' => $active]);
    }

    private function rate(ShippingMethod $m, Province $p, ?City $c = null, int $perKg = 10000, int $min = 25000, ?int $etdMin = 2, ?int $etdMax = 4): ShippingRate
    {
        return ShippingRate::create(['shipping_method_id' => $m->id, 'province_id' => $p->id, 'city_id' => $c?->id, 'rate_per_kg' => $perKg, 'min_charge' => $min, 'etd_min' => $etdMin, 'etd_max' => $etdMax]);
    }

    private function product(int $weight = 5000, int $price = 3000000, int $stock = 10): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Mesin S{$n}", 'slug' => "mesin-s{$n}", 'sku' => "S-{$n}", 'price' => $price, 'weight' => $weight, 'stock' => $stock, 'stock_threshold' => 1, 'is_active' => true]));
    }

    private function cartFor(Product $p, int $qty = 1): array
    {
        return ['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => $qty, 'weight' => $p->weight, 'image' => null, 'stock' => $p->stock]]];
    }

    private function payload(City $city, array $over = []): array
    {
        return array_merge([
            'idempotency_key' => 'k1', 'name' => 'Budi', 'phone' => '0812', 'address' => 'Jl. Kopi 1',
            'province_id' => $city->province_id, 'city_id' => $city->id, 'postal_code' => '40111',
        ], $over);
    }

    private function checkout(User $user, Product $p, array $payload, int $qty = 1, array $session = [])
    {
        return $this->actingAs($user)->withSession(array_merge($this->cartFor($p, $qty), ['checkout_key' => 'k1'], $session))
            ->post(route('customer.checkout.store'), $payload);
    }

    private function adminUser(string $role = 'admin'): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    // ================= perhitungan =================

    public function test_billable_weight_rounds_up_with_one_kg_minimum(): void
    {
        $this->assertSame(1, $this->shipping->billableKg(0));
        $this->assertSame(1, $this->shipping->billableKg(300));
        $this->assertSame(1, $this->shipping->billableKg(1000));
        $this->assertSame(2, $this->shipping->billableKg(1001));
        $this->assertSame(3, $this->shipping->billableKg(2500));
    }

    public function test_total_weight_uses_a_default_for_products_without_weight(): void
    {
        $this->assertSame(2 * 5000 + 3 * 1000, $this->shipping->totalWeight([['quantity' => 2, 'weight' => 5000], ['quantity' => 3, 'weight' => 0]]));
        $this->assertSame(1000, $this->shipping->totalWeight([['quantity' => 1]]));   // berat tak tercatat -> tidak jatuh ke Rp 0
    }

    public function test_cost_is_per_kg_with_a_minimum_charge(): void
    {
        [$prov, $city] = $this->region();
        $m = $this->method();
        $this->rate($m, $prov, null, 10000, 25000);

        $this->assertSame(25000, $this->shipping->options($city, 1000, 1000000)[0]['cost']);    // 1 kg = 10.000 < minimum 25.000
        $this->assertSame(30000, $this->shipping->options($city, 3000, 1000000)[0]['cost']);    // 3 kg
        $this->assertSame(40000, $this->shipping->options($city, 3001, 1000000)[0]['cost']);    // 4 kg (dibulatkan ke atas)
        $this->assertSame('2-4 hari', $this->shipping->options($city, 1000, 1)[0]['etd']);
    }

    public function test_city_rate_overrides_the_province_rate(): void
    {
        [$prov, $bandung] = $this->region();
        $cimahi = City::create(['province_id' => $prov->id, 'code' => '32.77', 'name' => 'Kota Cimahi']);
        $m = $this->method();
        $this->rate($m, $prov, null, 10000, 0);
        $this->rate($m, $prov, $bandung, 4000, 0, 1, 1);   // pengecualian: Bandung lebih murah

        $this->assertSame(8000, $this->shipping->options($bandung, 2000, 1)[0]['cost']);
        $this->assertSame('1 hari', $this->shipping->options($bandung, 2000, 1)[0]['etd']);
        $this->assertSame(20000, $this->shipping->options($cimahi, 2000, 1)[0]['cost']);   // pakai tarif provinsi
    }

    public function test_methods_without_a_rate_for_the_destination_or_inactive_are_not_offered(): void
    {
        [$prov, $city] = $this->region();
        [$otherProv] = $this->region('31', 'DKI Jakarta', '31.71', 'Kota Jakarta Selatan');

        $a = $this->method('Melayani Jabar');
        $this->rate($a, $prov);
        $b = $this->method('Hanya Jakarta');
        $this->rate($b, $otherProv);
        $c = $this->method('Nonaktif', false);
        $this->rate($c, $prov);
        $d = $this->method('Dihapus');
        $this->rate($d, $prov);
        $d->delete();

        $names = array_column($this->shipping->options($city, 1000, 1), 'name');
        $this->assertSame(['Melayani Jabar'], $names);
        $this->assertNull($this->shipping->quote($c->id, $city, 1000, 1));
        $this->assertNull($this->shipping->quote($b->id, $city, 1000, 1));
        $this->assertNull($this->shipping->quote(999999, $city, 1000, 1));
    }

    public function test_free_shipping_threshold_applies_to_the_amount_after_discount(): void
    {
        [$prov, $city] = $this->region();
        $this->rate($this->method(), $prov, null, 10000, 25000);
        Setting::set('free_shipping_min', 5000000);

        $below = $this->shipping->options($city, 2000, 4999999)[0];
        $this->assertSame(25000 > 20000 ? 25000 : 20000, $below['cost']);
        $this->assertFalse($below['free']);

        $at = $this->shipping->options($city, 2000, 5000000)[0];
        $this->assertSame(0, $at['cost']);
        $this->assertTrue($at['free']);

        Setting::set('free_shipping_min', 0);
        $this->assertFalse($this->shipping->options($city, 2000, 99000000)[0]['free']);   // 0 = fitur mati
    }

    public function test_configured_reflects_whether_any_active_method_has_rates(): void
    {
        [$prov] = $this->region();
        $this->assertFalse($this->shipping->configured());

        $m = $this->method();
        $this->assertFalse($this->shipping->configured());      // layanan ada, tarif belum

        $this->rate($m, $prov);
        $this->assertTrue($this->shipping->configured());

        $m->update(['is_active' => false]);
        $this->assertFalse($this->shipping->configured());
    }

    // ================= endpoint ongkir =================

    public function test_shipping_options_endpoint(): void
    {
        [$prov, $city] = $this->region();
        $this->rate($this->method('Kargo Reguler'), $prov, null, 10000, 25000, 2, 4);
        $user = User::factory()->create();
        $p = $this->product(3000);

        $this->getJson(route('customer.checkout.shipping', ['city_id' => $city->id]))->assertUnauthorized();   // guest dulu, sebelum actingAs

        $this->actingAs($user)->withSession($this->cartFor($p, 2))->getJson(route('customer.checkout.shipping', ['city_id' => $city->id]))
            ->assertOk()->assertJsonPath('options.0.name', 'Kargo Reguler')->assertJsonPath('options.0.cost', 60000)   // 6 kg x 10.000
            ->assertJsonPath('options.0.etd', '2-4 hari')->assertJsonPath('message', null);

        $this->actingAs($user)->withSession($this->cartFor($p))->getJson(route('customer.checkout.shipping', ['city_id' => 999999]))
            ->assertOk()->assertJsonCount(0, 'options');

    }

    public function test_shipping_options_endpoint_tells_when_destination_is_not_served(): void
    {
        [$prov] = $this->region();
        [, $far] = $this->region('94', 'Papua', '94.01', 'Kabupaten Jayapura');
        $this->rate($this->method(), $prov);
        $user = User::factory()->create();

        $this->actingAs($user)->withSession($this->cartFor($this->product()))->getJson(route('customer.checkout.shipping', ['city_id' => $far->id]))
            ->assertOk()->assertJsonCount(0, 'options')->assertJsonPath('message', 'Belum ada layanan pengiriman ke daerah ini. Silakan hubungi kami.');
    }

    // ================= checkout =================

    public function test_order_total_includes_shipping_and_stores_snapshots(): void
    {
        [$prov, $city] = $this->region();
        $m = $this->method('Kargo Reguler');
        $this->rate($m, $prov, null, 10000, 25000, 2, 4);
        $user = User::factory()->create();
        $p = $this->product(5000, 3000000);

        $this->checkout($user, $p, $this->payload($city, ['shipping_method_id' => $m->id]), 2)->assertSessionHasNoErrors();

        $o = Order::first();
        $this->assertSame('6000000.00', (string) $o->subtotal);
        $this->assertSame('100000.00', (string) $o->shipping_cost);        // 10 kg x 10.000
        $this->assertSame('6100000.00', (string) $o->total);
        $this->assertSame('Kargo Reguler', $o->shipping_method);
        $this->assertSame(10000, $o->shipping_weight);
        $this->assertSame('2-4 hari', $o->shipping_etd);
        $this->assertSame('Kota Bandung', $o->shipping_city);
        $this->assertSame('Jawa Barat', $o->shipping_province);
        $this->assertSame('40111', $o->shipping_postal_code);
        $this->assertSame('Jl. Kopi 1, Kota Bandung, Jawa Barat 40111', $o->full_shipping_address);

        $c = $user->customer;
        $this->assertSame($city->id, $c->city_id);
        $this->assertSame($prov->id, $c->province_id);
        $this->assertSame('Kota Bandung', $c->city);

        $this->actingAs($user)->get(route('customer.orders.show', $o->order_number))->assertOk()->assertSee('Kargo Reguler')->assertSee('2-4 hari')->assertSee('6.100.000');
    }

    public function test_the_shipping_price_cannot_be_tampered_with_from_the_browser(): void
    {
        [$prov, $city] = $this->region();
        $m = $this->method();
        $this->rate($m, $prov, null, 10000, 25000);
        $user = User::factory()->create();

        $this->checkout($user, $this->product(2000), $this->payload($city, ['shipping_method_id' => $m->id, 'shipping_cost' => 1, 'total' => 1, 'discount' => 999999]));

        $o = Order::first();
        $this->assertSame('25000.00', (string) $o->shipping_cost);
        $this->assertSame('0.00', (string) $o->discount);
        $this->assertNotSame('1.00', (string) $o->total);
    }

    public function test_a_service_is_required_when_shipping_is_configured_but_not_otherwise(): void
    {
        [$prov, $city] = $this->region();
        $user = User::factory()->create();
        $p = $this->product();

        // belum ada tarif sama sekali -> checkout jalan tanpa ongkir (diatur manual)
        $this->checkout($user, $p, $this->payload($city))->assertSessionHasNoErrors();
        $o = Order::first();
        $this->assertSame('0.00', (string) $o->shipping_cost);
        $this->assertNull($o->shipping_method);
        $this->assertSame('3000000.00', (string) $o->total);

        // sudah ada tarif -> layanan wajib dipilih
        $this->rate($this->method(), $prov);
        $this->checkout($user, $p, $this->payload($city, ['idempotency_key' => 'k2']), 1, ['checkout_key' => 'k2'])->assertSessionHasErrors('shipping_method_id');
        $this->assertSame(1, Order::count());
    }

    public function test_unavailable_service_is_rejected_and_rolls_back_the_voucher(): void
    {
        [$prov, $city] = $this->region();
        [$otherProv] = $this->region('31', 'DKI Jakarta', '31.71', 'Kota Jakarta Selatan');
        $served = $this->method('Melayani Jabar');
        $this->rate($served, $prov);
        $other = $this->method('Hanya Jakarta');
        $this->rate($other, $otherProv);
        $v = Voucher::create(['code' => 'HEMAT', 'type' => 'percentage', 'value' => 10, 'quota' => 5, 'per_user_limit' => 1, 'is_active' => true]);
        $user = User::factory()->create();

        $this->checkout($user, $this->product(), $this->payload($city, ['shipping_method_id' => $other->id]), 1, ['checkout_voucher' => 'HEMAT'])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, $v->fresh()->used_count);          // kuota voucher ikut dibatalkan (transaksi di-rollback)
    }

    public function test_city_must_belong_to_the_selected_province_and_postal_code_must_be_5_digits(): void
    {
        [$prov, $city] = $this->region();
        [$otherProv] = $this->region('31', 'DKI Jakarta', '31.71', 'Kota Jakarta Selatan');
        $user = User::factory()->create();
        $p = $this->product();

        $this->checkout($user, $p, $this->payload($city, ['province_id' => $otherProv->id]))->assertSessionHasErrors('city_id');
        $this->checkout($user, $p, $this->payload($city, ['city_id' => 999999]))->assertSessionHasErrors('city_id');
        $this->checkout($user, $p, $this->payload($city, ['postal_code' => '123']))->assertSessionHasErrors('postal_code');
        $this->checkout($user, $p, $this->payload($city, ['postal_code' => 'abcde']))->assertSessionHasErrors('postal_code');
        $this->assertSame(0, Order::count());
    }

    public function test_free_shipping_is_judged_after_the_voucher_discount(): void
    {
        [$prov, $city] = $this->region();
        $m = $this->method();
        $this->rate($m, $prov, null, 10000, 25000);
        Setting::set('free_shipping_min', 5000000);
        Voucher::create(['code' => 'DISKON20', 'type' => 'percentage', 'value' => 20, 'is_active' => true, 'per_user_limit' => 0]);
        $user = User::factory()->create();
        $p = $this->product(1000, 6000000);

        // tanpa voucher: 6 jt >= 5 jt -> gratis ongkir
        $this->checkout($user, $p, $this->payload($city, ['shipping_method_id' => $m->id]));
        $this->assertSame('0.00', (string) Order::first()->shipping_cost);
        $this->assertSame('6000000.00', (string) Order::first()->total);

        // dengan voucher 20%: 6 jt - 1,2 jt = 4,8 jt < 5 jt -> ongkir dikenakan
        $this->checkout($user, $p, $this->payload($city, ['shipping_method_id' => $m->id, 'idempotency_key' => 'k2']), 1, ['checkout_key' => 'k2', 'checkout_voucher' => 'DISKON20']);
        $o = Order::latest('id')->first();
        $this->assertSame('1200000.00', (string) $o->discount);
        $this->assertSame('25000.00', (string) $o->shipping_cost);
        $this->assertSame('4825000.00', (string) $o->total);       // 6.000.000 - 1.200.000 + 25.000
    }

    public function test_checkout_page_shows_the_shipping_section_only_when_relevant(): void
    {
        [$prov, $city] = $this->region();
        $user = User::factory()->create();
        $p = $this->product();

        $this->actingAs($user)->withSession($this->cartFor($p))->get(route('customer.checkout.index'))
            ->assertOk()->assertSee('Layanan Pengiriman')->assertSee('dikonfirmasi oleh tim kami')->assertSee('Kota Bandung')->assertSee('Jawa Barat');

        $this->rate($this->method(), $prov);
        $this->actingAs($user)->withSession($this->cartFor($p))->get(route('customer.checkout.index'))
            ->assertOk()->assertSee('Layanan Pengiriman')->assertSee('name="city_id"', false)->assertSee('name="province_id"', false);
    }

    public function test_legacy_orders_keep_their_full_address(): void
    {
        $order = new Order(['shipping_address' => 'Jl. Lama 5, Jakarta, DKI Jakarta 10110']);   // sebelum alamat terstruktur
        $this->assertSame('Jl. Lama 5, Jakarta, DKI Jakarta 10110', $order->full_shipping_address);

        $new = new Order(['shipping_address' => 'Jl. Baru 1', 'shipping_city' => 'Kota Bandung', 'shipping_province' => 'Jawa Barat', 'shipping_postal_code' => '40111']);
        $this->assertSame('Jl. Baru 1, Kota Bandung, Jawa Barat 40111', $new->full_shipping_address);
    }

    // ================= profil =================

    public function test_profile_saves_province_and_city_and_rejects_mismatch(): void
    {
        [$prov, $city] = $this->region();
        [$otherProv] = $this->region('31', 'DKI Jakarta', '31.71', 'Kota Jakarta Selatan');
        $user = User::factory()->create();
        $base = ['name' => $user->name, 'email' => $user->email, 'phone' => '0812', 'address' => 'Jl. A 1'];

        $this->actingAs($user)->patch(route('customer.profile.update'), $base + ['province_id' => $prov->id, 'city_id' => $city->id, 'postal_code' => '40111'])->assertSessionHasNoErrors();
        $c = $user->fresh()->customer;
        $this->assertSame($city->id, $c->city_id);
        $this->assertSame('Kota Bandung', $c->city);
        $this->assertSame('Jawa Barat', $c->province);

        $this->actingAs($user)->patch(route('customer.profile.update'), $base + ['province_id' => $otherProv->id, 'city_id' => $city->id])->assertSessionHasErrors('city_id');
        $this->actingAs($user)->patch(route('customer.profile.update'), $base + ['postal_code' => '12'])->assertSessionHasErrors('postal_code');

        $this->actingAs($user)->get(route('customer.profile.edit'))->assertOk()->assertSee('name="city_id"', false);
    }

    // ================= admin =================

    public function test_admin_manages_methods_and_rates(): void
    {
        [$prov, $city] = $this->region();
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.shipping.store'), ['name' => 'Kargo Reguler', 'description' => 'Via ekspedisi', 'is_active' => 1])->assertSessionHasNoErrors();
        $m = ShippingMethod::first();
        $this->actingAs($admin)->get(route('admin.shipping.edit', $m->id))->assertOk()->assertSee('Kargo Reguler');

        $rate = ['province_id' => $prov->id, 'rate_per_kg' => 9000, 'min_charge' => 20000, 'etd_min' => 2, 'etd_max' => 4];
        $this->actingAs($admin)->post(route('admin.shipping.rates.store', $m->id), $rate)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.shipping.rates.store', $m->id), ['rate_per_kg' => 12000] + $rate);   // sama -> diperbarui, bukan dobel
        $this->assertSame(1, ShippingRate::count());
        $this->assertSame(12000, (int) ShippingRate::first()->rate_per_kg);

        $this->actingAs($admin)->post(route('admin.shipping.rates.store', $m->id), $rate + ['city_id' => $city->id]);   // pengecualian kota
        $this->assertSame(2, ShippingRate::count());

        $this->actingAs($admin)->get(route('admin.shipping.index'))->assertOk()->assertSee('Kargo Reguler')->assertSee('2 tarif wilayah');
        $this->actingAs($admin)->get(route('admin.shipping.edit', $m->id))->assertOk()->assertSee('Jawa Barat')->assertSee('Kota Bandung');

        $this->actingAs($admin)->delete(route('admin.shipping.rates.destroy', [$m->id, ShippingRate::first()->id]))->assertSessionHas('success');
        $this->assertSame(1, ShippingRate::count());

        $this->actingAs($admin)->put(route('admin.shipping.update', $m->id), ['name' => 'Kargo Baru', 'sort_order' => 3])->assertSessionHasNoErrors();
        $this->assertSame('Kargo Baru', $m->fresh()->name);
        $this->assertFalse($m->fresh()->is_active);       // checkbox tidak dicentang

        $this->actingAs($admin)->delete(route('admin.shipping.destroy', $m->id));
        $this->assertSoftDeleted($m);
    }

    public function test_rate_validation(): void
    {
        [$prov, $city] = $this->region();
        [$otherProv] = $this->region('31', 'DKI Jakarta', '31.71', 'Kota Jakarta Selatan');
        $m = $this->method();
        $admin = $this->adminUser();
        $url = route('admin.shipping.rates.store', $m->id);
        $ok = ['province_id' => $prov->id, 'rate_per_kg' => 5000];

        $this->actingAs($admin)->post($url, ['province_id' => $prov->id])->assertSessionHasErrors('rate_per_kg');
        $this->actingAs($admin)->post($url, ['rate_per_kg' => -1] + $ok)->assertSessionHasErrors('rate_per_kg');
        $this->actingAs($admin)->post($url, ['province_id' => $otherProv->id, 'city_id' => $city->id, 'rate_per_kg' => 1])->assertSessionHasErrors('city_id');
        $this->actingAs($admin)->post($url, ['etd_min' => 5, 'etd_max' => 2] + $ok)->assertSessionHasErrors('etd_max');
        $this->actingAs($admin)->post($url, ['province_id' => 999999, 'rate_per_kg' => 1])->assertSessionHasErrors('province_id');
        $this->assertSame(0, ShippingRate::count());
    }

    public function test_a_rate_of_another_method_cannot_be_deleted_through_this_one(): void
    {
        [$prov] = $this->region();
        $a = $this->method('A');
        $b = $this->method('B');
        $rateOfB = $this->rate($b, $prov);

        $this->actingAs($this->adminUser())->delete(route('admin.shipping.rates.destroy', [$a->id, $rateOfB->id]))->assertNotFound();
        $this->assertSame(1, ShippingRate::count());
    }

    public function test_bulk_rates_fill_every_province_and_respect_overwrite(): void
    {
        $this->seed(WilayahSeeder::class);
        $m = $this->method();
        $admin = $this->adminUser();
        $jabar = Province::where('name', 'Jawa Barat')->first();
        $this->rate($m, $jabar, null, 1234, 0);          // sudah punya tarif

        $this->actingAs($admin)->post(route('admin.shipping.rates.bulk', $m->id), ['rate_per_kg' => 20000, 'min_charge' => 50000, 'etd_min' => 3, 'etd_max' => 7])->assertSessionHas('success');
        $this->assertSame(38, ShippingRate::where('shipping_method_id', $m->id)->whereNull('city_id')->count());
        $this->assertSame(1234, (int) ShippingRate::where('province_id', $jabar->id)->first()->rate_per_kg);   // dilewati

        $this->actingAs($admin)->post(route('admin.shipping.rates.bulk', $m->id), ['rate_per_kg' => 20000, 'overwrite' => 1]);
        $this->assertSame(38, ShippingRate::where('shipping_method_id', $m->id)->whereNull('city_id')->count());   // tidak dobel
        $this->assertSame(20000, (int) ShippingRate::where('province_id', $jabar->id)->first()->rate_per_kg);     // ditimpa

        $this->actingAs($admin)->post(route('admin.shipping.rates.bulk', $m->id), [])->assertSessionHasErrors('rate_per_kg');
    }

    public function test_only_authorised_roles_can_manage_shipping(): void
    {
        $m = $this->method();
        $staff = $this->adminUser('staff');

        $this->actingAs($staff)->get(route('admin.shipping.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.shipping.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->adminUser('customer'))->get(route('admin.shipping.index'))->assertForbidden();
        $this->actingAs($this->adminUser('admin'))->get(route('admin.shipping.index'))->assertOk();
        $this->actingAs($this->adminUser('admin'))->get(route('admin.shipping.create'))->assertOk();
    }

    public function test_admin_is_warned_until_shipping_is_configured(): void
    {
        [$prov] = $this->region();
        $admin = $this->adminUser();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Ongkir belum diatur');
        $this->actingAs($admin)->get(route('admin.shipping.index'))->assertOk()->assertSee('Ongkir belum diatur');

        $this->rate($this->method(), $prov);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('Ongkir belum diatur');
        $this->actingAs($admin)->get(route('admin.shipping.index'))->assertDontSee('Ongkir belum diatur');
    }

    public function test_free_shipping_threshold_setting_is_editable_and_validated(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Gratis ongkir untuk belanja minimal');
        $this->actingAs($admin)->put(route('admin.settings.update'), ['free_shipping_min' => '7500000'])->assertSessionHasNoErrors();
        $this->assertSame(7500000, $this->shipping->freeShippingMin());

        $this->actingAs($admin)->put(route('admin.settings.update'), ['free_shipping_min' => 'banyak'])->assertSessionHasErrors('free_shipping_min');
        $this->actingAs($admin)->put(route('admin.settings.update'), ['free_shipping_min' => '-5'])->assertSessionHasErrors('free_shipping_min');
    }

    // ================= data wilayah =================

    public function test_region_seeder_loads_all_provinces_and_cities_and_is_repeatable(): void
    {
        $this->seed(WilayahSeeder::class);
        $this->assertSame(38, Province::count());
        $this->assertSame(514, City::count());

        $this->seed(WilayahSeeder::class);                         // tidak menggandakan
        $this->assertSame(38, Province::count());
        $this->assertSame(514, City::count());

        $jabar = Province::where('name', 'Jawa Barat')->first();
        $this->assertNotNull($jabar);
        $this->assertTrue($jabar->cities()->where('name', 'Kota Bandung')->exists());
        $this->assertSame(0, City::whereDoesntHave('province')->count());
        $this->assertSame(0, Province::whereRaw('name != TRIM(name)')->count() + City::whereRaw('name != TRIM(name)')->count());   // tanpa spasi nyasar
    }

    public function test_region_picker_groups_cities_by_province(): void
    {
        $this->seed(WilayahSeeder::class);
        Cache::forget('regions.picker');

        $picker = Regions::picker();
        $jabar = Province::where('name', 'Jawa Barat')->first();

        $this->assertCount(38, $picker['provinces']);
        $this->assertCount(count($picker['cities'][$jabar->id]), $jabar->cities);
        $this->assertSame(514, array_sum(array_map('count', $picker['cities'])));
    }

    public function test_demo_seeder_creates_a_complete_example_tariff(): void
    {
        $this->seed(ShippingDemoSeeder::class);

        $this->assertSame(1, ShippingMethod::count());
        $this->assertSame(38, ShippingRate::count());
        $this->assertTrue($this->shipping->configured());

        $this->seed(ShippingDemoSeeder::class);
        $this->assertSame(38, ShippingRate::count());              // aman diulang

        $bandung = City::where('name', 'Kota Bandung')->first();
        $this->assertCount(1, $this->shipping->options($bandung, 1000, 1));
    }
}
