<?php

namespace Tests\Feature\FlashSale;

use App\Exceptions\FlashSaleChanged;
use App\Models\City;
use App\Models\Customer;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use App\Services\CartService;
use App\Services\FlashSaleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Flash sale: harga promo ditegakkan di server, kuota atomik, jadwal, etalase, dan admin. */
class FlashSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userAs(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    private function product(float $price = 1000000, int $stock = 50): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Grinder FS{$n}", 'slug' => "grinder-fs{$n}", 'sku' => "FS-{$n}", 'price' => $price, 'cost_price' => $price * 0.6, 'stock' => $stock, 'stock_threshold' => 1, 'is_active' => true]));
    }

    /** @return array{0:FlashSale,1:FlashSaleItem} */
    private function sale(Product $p, float $salePrice = 750000, int $quota = 5, ?int $max = null, string $from = '-1 hour', string $to = '+3 hours', bool $active = true): array
    {
        $s = FlashSale::create(['name' => 'Promo Uji', 'starts_at' => now()->modify($from), 'ends_at' => now()->modify($to), 'is_active' => $active]);
        $i = $s->items()->create(['product_id' => $p->id, 'sale_price' => $salePrice, 'quota' => $quota, 'max_per_order' => $max]);
        app(FlashSaleService::class)->flush();

        return [$s, $i];
    }

    private function boughtOrder(FlashSaleItem $i, Product $p, int $qty, string $status = 'menunggu_bayar'): Order
    {
        $u = User::factory()->create();
        $c = Customer::unguarded(fn () => Customer::create(['user_id' => $u->id, 'name' => $u->name, 'email' => $u->email]));
        $o = Order::unguarded(fn () => Order::create(['order_number' => Order::generateOrderNumber(), 'customer_id' => $c->id, 'user_id' => $u->id, 'status' => $status, 'subtotal' => 1, 'discount' => 0, 'shipping_cost' => 0, 'total' => 1, 'shipping_name' => 'B']));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => $i->sale_price, 'quantity' => $qty, 'subtotal' => 1, 'flash_sale_item_id' => $i->id]));
        app(FlashSaleService::class)->flush();

        return $o;
    }

    private function checkout(User $u, Product $p, int $qty, ?float $tamperPrice = null)
    {
        $prov = Province::firstOrCreate(['code' => '32'], ['name' => 'Jawa Barat']);
        $city = City::firstOrCreate(['code' => '32.73'], ['province_id' => $prov->id, 'name' => 'Kota Bandung']);
        $key = 'k'.uniqid();
        app(FlashSaleService::class)->flush();
        $price = $tamperPrice ?? $p->currentPrice();   // seperti add-to-cart: harga yang berlaku saat ini

        return $this->actingAs($u)->withSession(['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $price, 'quantity' => $qty, 'weight' => 1000, 'image' => null, 'stock' => $p->stock]], 'checkout_key' => $key])
            ->post(route('customer.checkout.store'), ['terms' => '1', 'idempotency_key' => $key, 'name' => 'Budi', 'phone' => '081234567890', 'address' => 'Jl. Kopi 1', 'province_id' => $prov->id, 'city_id' => $city->id, 'postal_code' => '40111']);
    }

    // ================= jadwal & kuota =================

    public function test_promo_is_live_only_inside_the_window_when_active_and_with_quota(): void
    {
        $svc = app(FlashSaleService::class);

        [, $live] = $this->sale($a = $this->product());
        $this->assertSame($live->id, $svc->itemFor($a->id)->id);
        $this->assertEquals(750000, $svc->priceFor($a));
        $this->assertEquals(750000, $a->currentPrice());

        $this->sale($b = $this->product(), from: '+1 hour', to: '+2 hours');          // terjadwal
        $this->sale($c = $this->product(), from: '-3 hours', to: '-1 hour');          // selesai
        $this->sale($d = $this->product(), active: false);                            // dinonaktifkan
        foreach ([$b, $c, $d] as $p) {
            $this->assertNull($svc->itemFor($p->id));
            $this->assertEquals(1000000, $p->currentPrice());
        }
    }

    public function test_sold_quota_counts_live_orders_and_cancelled_orders_release_it(): void
    {
        $p = $this->product();
        [, $i] = $this->sale($p, quota: 5);
        $svc = app(FlashSaleService::class);

        $this->boughtOrder($i, $p, 2, 'menunggu_bayar');
        $this->boughtOrder($i, $p, 1, 'selesai');
        $cancelled = $this->boughtOrder($i, $p, 2, 'batal');

        $this->assertSame(3, $svc->items()->get($p->id)->sold);
        $this->assertSame(2, $svc->items()->get($p->id)->remaining);
        $this->assertSame([$i->id => 3], $svc->soldFor([$i->id]));

        $cancelled->update(['status' => 'batal']);
        $this->boughtOrder($i, $p, 2, 'menunggu_bayar');                                // memakai sisa kuota
        $this->assertNull($svc->itemFor($p->id));                                       // habis -> harga normal
        $this->assertEquals(1000000, $p->currentPrice());
    }

    // ================= keranjang =================

    public function test_cart_price_comes_from_the_database_and_cannot_be_tampered_with(): void
    {
        $p = $this->product();
        [, $i] = $this->sale($p, 750000, 5);

        session()->put('cart', [$p->id => ['id' => $p->id, 'name' => 'x', 'price' => 1, 'quantity' => 2, 'weight' => 1, 'sku' => 's', 'slug' => 'x', 'image' => null, 'stock' => 9]]);
        $res = app(CartService::class)->refresh();

        $this->assertEquals(750000, $res['cart'][$p->id]['price']);
        $this->assertEquals(1000000, $res['cart'][$p->id]['original_price']);
        $this->assertSame($i->id, $res['cart'][$p->id]['flash_item_id']);
        $this->assertEquals(1500000, $res['total']);
    }

    public function test_cart_caps_quantity_to_the_per_order_limit_and_remaining_quota(): void
    {
        $p = $this->product();
        [, $i] = $this->sale($p, 750000, 10, 2);
        session()->put('cart', [$p->id => ['id' => $p->id, 'name' => 'x', 'price' => 750000, 'quantity' => 5, 'weight' => 1, 'sku' => 's', 'slug' => 'x', 'image' => null, 'stock' => 9]]);

        $res = app(CartService::class)->refresh();

        $this->assertSame(2, $res['cart'][$p->id]['quantity']);
        $this->assertStringContainsString('batas/kuota promo', implode(' ', $res['notices']));

        $this->boughtOrder($i, $p, 9);                                                  // sisa kuota 1
        session()->put('cart', [$p->id => ['id' => $p->id, 'name' => 'x', 'price' => 750000, 'quantity' => 2, 'weight' => 1, 'sku' => 's', 'slug' => 'x', 'image' => null, 'stock' => 9]]);
        $this->assertSame(1, app(CartService::class)->refresh()['cart'][$p->id]['quantity']);
    }

    public function test_cart_returns_to_the_normal_price_when_the_promo_ends_or_runs_out(): void
    {
        $p = $this->product();
        [$s, $i] = $this->sale($p, 750000, 2);
        $line = fn () => [$p->id => ['id' => $p->id, 'name' => 'x', 'price' => 750000, 'quantity' => 1, 'weight' => 1, 'sku' => 's', 'slug' => 'x', 'image' => null, 'stock' => 9]];

        session()->put('cart', $line());
        $this->assertEquals(750000, app(CartService::class)->refresh()['cart'][$p->id]['price']);

        $s->update(['ends_at' => now()->subMinute()]);
        app(FlashSaleService::class)->flush();
        session()->put('cart', $line());
        $res = app(CartService::class)->refresh();
        $this->assertEquals(1000000, $res['cart'][$p->id]['price']);
        $this->assertNull($res['cart'][$p->id]['flash_item_id']);
        $this->assertNotEmpty($res['notices']);                                         // pembeli diberi tahu harga berubah
    }

    public function test_add_to_cart_uses_the_promo_price_and_the_cart_page_marks_it(): void
    {
        $p = $this->product();
        $this->sale($p, 750000, 5);
        $u = $this->userAs('customer');

        $this->actingAs($u)->post(route('customer.cart.store'), ['product_id' => $p->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(750000, session('cart')[$p->id]['price']);

        $this->actingAs($u)->get(route('customer.cart.index'))->assertOk()->assertSee('Flash Sale')->assertSee('750.000');
    }

    // ================= checkout =================

    public function test_checkout_charges_the_promo_price_ignores_a_tampered_cart_and_records_the_item(): void
    {
        $p = $this->product();
        [, $i] = $this->sale($p, 750000, 5);

        $buyer = $this->userAs('customer');

        // keranjang dengan harga dimanipulasi: harga dihitung ulang dari database, checkout minta konfirmasi dan tidak ada pesanan
        $this->checkout($buyer, $p, 2, tamperPrice: 1)->assertRedirect(route('customer.checkout.index'));
        $this->assertSame(0, Order::count());

        $this->checkout($buyer, $p, 2)->assertSessionHasNoErrors();

        $o = Order::firstOrFail();
        $item = $o->items->first();
        $this->assertEquals(750000, $item->price);
        $this->assertEquals(1500000, $o->subtotal);
        $this->assertEquals(600000, $item->cost_price);                                 // HPP tetap HPP produk, bukan harga promo
        $this->assertSame($i->id, $item->flash_sale_item_id);
        $this->assertSame(2, app(FlashSaleService::class)->soldFor([$i->id])[$i->id]);
    }

    public function test_second_buyer_cannot_exceed_the_quota(): void
    {
        $p = $this->product();
        $this->sale($p, 750000, 3);

        $this->checkout($this->userAs('customer'), $p, 2)->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());

        // pembeli kedua minta 2, kuota tinggal 1: keranjang dikoreksi dan checkout minta konfirmasi ulang (tidak ada pesanan)
        $this->checkout($this->userAs('customer'), $p, 2)->assertRedirect(route('customer.checkout.index'));
        $this->assertSame(1, Order::count());

        $this->checkout($this->userAs('customer'), $p, 1)->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
        app(FlashSaleService::class)->flush();                                          // memo per request di test berbagi satu request
        $this->assertNull(app(FlashSaleService::class)->itemFor($p->id));               // habis
    }

    public function test_reserve_rejects_stale_promo_lines_inside_the_transaction(): void
    {
        $p = $this->product();
        [$s, $i] = $this->sale($p, 750000, 3, 2);
        $svc = app(FlashSaleService::class);
        $line = fn (array $x = []) => [$p->id => array_merge(['id' => $p->id, 'name' => 'Grinder', 'price' => 750000, 'quantity' => 1, 'flash_item_id' => $i->id], $x)];

        $svc->reserve($line());                                                          // lolos
        $svc->reserve([$p->id => ['id' => $p->id, 'name' => 'x', 'price' => 1000000, 'quantity' => 9, 'flash_item_id' => null]]); // bukan promo: dilewati

        foreach ([
            ['quantity' => 3],                                                           // melebihi maks/pesanan
            ['price' => 700000],                                                         // harga tidak cocok
        ] as $bad) {
            try {
                $svc->reserve($line($bad));
                $this->fail('Seharusnya ditolak');
            } catch (FlashSaleChanged $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $this->boughtOrder($i, $p, 3);                                                   // kuota habis
        $this->expectException(FlashSaleChanged::class);
        $svc->reserve($line());
    }

    public function test_reserve_rejects_when_the_promo_has_ended(): void
    {
        $p = $this->product();
        [$s, $i] = $this->sale($p, 750000, 3);
        $s->update(['ends_at' => now()->subSecond()]);

        $this->expectException(FlashSaleChanged::class);
        app(FlashSaleService::class)->reserve([$p->id => ['id' => $p->id, 'name' => 'x', 'price' => 750000, 'quantity' => 1, 'flash_item_id' => $i->id]]);
    }

    // ================= etalase =================

    public function test_storefront_shows_the_campaign_with_discount_progress_and_countdown(): void
    {
        $p = $this->product(1000000);
        [$s, $i] = $this->sale($p, 750000, 10);
        $this->boughtOrder($i, $p, 4);

        $res = $this->get(route('products.index'))->assertOk();
        $res->assertSee('Flash Sale')->assertSee('Promo Uji')->assertSee('-25%')->assertSee('750.000')->assertSee('Terjual 4 dari 10')->assertSee('Berakhir dalam')
            ->assertSee('flashTimer('.($s->ends_at->timestamp * 1000), false);
        $res->assertSee(route('products.index', ['flash' => 1]), false);
        $res->assertSee('Lihat semua')->assertDontSee('Lihat semua →')->assertDontSee('M13 2 4 14h6l-1 8 9-12h-6l1-8z', false);   // tanpa panah dan tanpa ikon petir
    }

    public function test_showcase_lists_every_running_promo_across_campaigns_like_the_see_all_page(): void
    {
        $products = [$a = $this->product(), $b = $this->product(), $c = $this->product(), $d = $this->product()];
        $this->sale($a, 750000, 5, null, '-1 hour', '+5 hours');                          // kampanye 1
        $this->sale($b, 800000, 5, null, '-1 hour', '+2 hours');                          // kampanye 2 (berakhir lebih cepat)
        $this->sale($c, 850000, 5, null, '-1 hour', '+9 hours');                          // kampanye 3
        $this->sale($d, 900000, 5, null, '+1 hour', '+3 hours');                          // belum mulai: tidak tampil

        $showcase = app(FlashSaleService::class)->showcase();
        $this->assertCount(3, $showcase['items']);
        $this->assertSame(3, $showcase['campaigns']);
        $this->assertNull($showcase['name']);                                              // beberapa kampanye: tanpa nama tunggal
        $this->assertSame($b->id, $showcase['items']->first()->product_id);               // yang paling cepat berakhir dulu
        $this->assertSame($showcase['items']->min(fn ($i) => $i->sale->ends_at->timestamp), $showcase['ends_at']->timestamp);

        $res = $this->get(route('products.index'))->assertOk();
        foreach ([$a, $b, $c] as $p) {
            $res->assertSee($p->name);
        }
        $res->assertDontSee('Promo Uji');
        // sama persis dengan halaman "Lihat semua"
        $all = $this->get(route('products.index', ['flash' => 1]))->assertOk();
        foreach ([$a, $b, $c] as $p) {
            $all->assertSee($p->name);
        }
        $all->assertDontSee($d->name);
    }

    public function test_showcase_puts_sold_out_last_hides_inactive_products_and_caps_at_twelve(): void
    {
        $out = $this->product();
        [, $oi] = $this->sale($out, 750000, 1, null, '-1 hour', '+1 hour');               // paling cepat berakhir tapi habis
        $this->boughtOrder($oi, $out, 1);
        $ok = $this->product();
        $this->sale($ok, 750000, 5, null, '-1 hour', '+8 hours');
        $hidden = $this->product();
        $this->sale($hidden, 750000, 5);
        $hidden->update(['is_active' => false]);
        app(FlashSaleService::class)->flush();

        $items = app(FlashSaleService::class)->showcase()['items'];
        $this->assertSame([$ok->id, $out->id], $items->pluck('product_id')->all());

        foreach (range(1, 14) as $n) {
            $this->sale($this->product(), 750000, 5);
        }
        $this->assertCount(12, app(FlashSaleService::class)->showcase(12)['items']);
    }

    public function test_single_campaign_keeps_its_name_and_the_timer_uses_the_earliest_end(): void
    {
        [$s1] = $this->sale($a = $this->product(), 750000, 5, null, '-1 hour', '+6 hours');
        $this->sale($b = $this->product(), 750000, 5, null, '-1 hour', '+2 hours');
        FlashSale::where('id', $s1->id)->update(['name' => 'Akhir bulan']);
        $earliest = FlashSale::where('id', '!=', $s1->id)->first()->ends_at;
        app(FlashSaleService::class)->flush();

        $res = $this->get(route('products.index'))->assertOk()->assertSee('flashTimer('.($earliest->timestamp * 1000), false);
        $res->assertDontSee('Akhir bulan');                                               // dua kampanye: nama disembunyikan
    }

    public function test_storefront_hides_the_section_without_a_live_campaign(): void
    {
        $this->get(route('products.index'))->assertOk()->assertDontSee('Berakhir dalam');

        $this->sale($this->product(), from: '+1 hour', to: '+2 hours');
        $this->get(route('products.index'))->assertOk()->assertDontSee('Berakhir dalam');

        $this->sale($this->product(), from: '-3 hours', to: '-1 hour');
        $this->get(route('products.index'))->assertOk()->assertDontSee('Berakhir dalam');
    }

    public function test_product_page_and_card_show_the_promo_and_flash_filter_lists_only_promo_products(): void
    {
        $promo = $this->product(1000000);
        $plain = $this->product(500000);
        $this->sale($promo, 750000, 5, 2);

        $this->get(route('products.show', $promo->slug))->assertOk()->assertSee('750.000')->assertSee('-25% Flash Sale')->assertSee('maks 2/pesanan');
        $this->get(route('products.show', $plain->slug))->assertOk()->assertDontSee('Flash Sale');
        $this->get(route('products.index', ['flash' => 1]))->assertOk()->assertSee($promo->name)->assertDontSee($plain->name);
    }

    public function test_sold_out_promo_items_are_marked_in_the_showcase(): void
    {
        $p = $this->product();
        [, $i] = $this->sale($p, 750000, 2);
        $this->boughtOrder($i, $p, 2);

        $this->get(route('products.index'))->assertOk()->assertSee('Habis')->assertSee('Terjual 2 dari 2');
    }

    // ================= admin =================

    private function payload(Product $p, array $over = []): array
    {
        return array_merge([
            'name' => 'Promo Akhir Pekan', 'starts_at' => now('Asia/Jakarta')->addHour()->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addHours(5)->format('Y-m-d\TH:i'), 'is_active' => '1',
            'items' => [['product_id' => $p->id, 'sale_price' => 800000, 'quota' => 10, 'max_per_order' => 2]],
        ], $over);
    }

    public function test_admin_creates_a_campaign_with_wib_times_converted_to_utc(): void
    {
        $p = $this->product();
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('admin.flash-sales.store'), $this->payload($p, ['starts_at' => '2026-12-12T10:00', 'ends_at' => '2026-12-12T22:00']))->assertSessionHasNoErrors();

        $s = FlashSale::firstOrFail();
        $this->assertSame('2026-12-12 03:00:00', $s->starts_at->format('Y-m-d H:i:s'));     // 10:00 WIB = 03:00 UTC
        $this->assertSame('2026-12-12 15:00:00', $s->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $s->items()->count());
        $this->assertSame(2, $s->items->first()->max_per_order);
        $this->actingAs($admin)->get(route('admin.flash-sales.index'))->assertOk()->assertSee('Promo Akhir Pekan')->assertSee('Terjadwal');
        $this->actingAs($admin)->get(route('admin.flash-sales.edit', $s->id))->assertOk()->assertSee('2026-12-12T10:00', false);
    }

    public function test_money_inputs_in_admin_forms_format_thousands(): void
    {
        $admin = $this->userAs('admin');
        $p = $this->product();

        $this->actingAs($admin)->get(route('admin.flash-sales.create'))->assertOk()->assertSee('data-rupiah', false)->assertSee('fmt(', false);
        $this->actingAs($admin)->get(route('admin.expenses.create'))->assertOk()->assertSee('data-rupiah', false);
        $this->actingAs($admin)->get(route('admin.finance.costs'))->assertOk()->assertSee('data-rupiah', false);
    }

    public function test_prices_with_indonesian_thousand_separators_are_read_correctly_on_the_server(): void
    {
        // Jaga-jaga bila JavaScript halaman gagal: "285.000" harus menjadi 285000, bukan 285.
        $admin = $this->userAs('admin');
        $p = $this->product(1000000);
        $items = fn ($price) => [['product_id' => $p->id, 'sale_price' => $price, 'quota' => 5]];

        $this->actingAs($admin)->post(route('admin.flash-sales.store'), $this->payload($p, ['items' => $items('285.000')]))->assertSessionHasNoErrors();
        $this->assertEquals(285000, FlashSale::firstOrFail()->items->first()->sale_price);

        $this->actingAs($admin)->post(route('admin.flash-sales.store'), $this->payload($p, ['name' => 'Lain', 'starts_at' => now('Asia/Jakarta')->addDays(3)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addDays(3)->addHours(2)->format('Y-m-d\TH:i'), 'items' => $items('abc')]))->assertSessionHasErrors('items.0.sale_price');
        $this->assertSame(1, FlashSale::count());
    }

    public function test_rupiah_cleaner(): void
    {
        foreach ([['285.000', '285000'], ['1.250.000', '1250000'], ['1.250.000,50', '1250000.50'], ['285000', '285000'], ['12.5', '12.5'], ['abc', 'abc'], ['1.00', '1.00']] as [$in, $out]) {
            $this->assertSame($out, \App\Support\Rupiah::clean($in), $in);
        }
        $this->assertSame(5, \App\Support\Rupiah::clean(5));
        $this->assertNull(\App\Support\Rupiah::clean(null));
    }

    public function test_other_money_forms_also_accept_formatted_numbers(): void
    {
        $keu = $this->userAs('keuangan');
        $p = $this->product();

        $this->actingAs($keu)->post(route('admin.expenses.store'), ['expense_date' => '2026-11-05', 'category' => 'sewa', 'description' => 'Sewa', 'amount' => '3.500.000'])->assertSessionHasNoErrors();
        $this->assertEquals(3500000, \App\Models\Expense::firstOrFail()->amount);

        $this->actingAs($keu)->put(route('admin.finance.costs.update'), ['costs' => [$p->id => '650.000']])->assertSessionHasNoErrors();
        $this->assertEquals(650000, $p->fresh()->cost_price);
    }

    public function test_admin_validation_rules(): void
    {
        $p = $this->product(1000000);
        $admin = $this->userAs('admin');
        $post = fn (array $o) => $this->actingAs($admin)->post(route('admin.flash-sales.store'), $this->payload($p, $o));

        $post(['items' => [['product_id' => $p->id, 'sale_price' => 1000000, 'quota' => 5]]])->assertSessionHasErrors('items.0.sale_price');    // tidak lebih murah
        $post(['items' => [['product_id' => $p->id, 'sale_price' => 1200000, 'quota' => 5]]])->assertSessionHasErrors('items.0.sale_price');
        $post(['items' => [['product_id' => $p->id, 'sale_price' => 500000, 'quota' => 0]]])->assertSessionHasErrors('items.0.quota');
        $post(['items' => []])->assertSessionHasErrors('items');
        $post(['items' => [['product_id' => 99999, 'sale_price' => 5, 'quota' => 1]]])->assertSessionHasErrors('items.0.product_id');
        $post(['items' => [['product_id' => $p->id, 'sale_price' => 5, 'quota' => 1], ['product_id' => $p->id, 'sale_price' => 5, 'quota' => 1]]])->assertSessionHasErrors();
        $post(['ends_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i'), 'starts_at' => now('Asia/Jakarta')->addHour()->format('Y-m-d\TH:i')])->assertSessionHasErrors('ends_at');
        $post(['starts_at' => '2026-12-01T00:00', 'ends_at' => '2027-02-01T00:00'])->assertSessionHasErrors('ends_at');                         // > 31 hari
        $post(['name' => ''])->assertSessionHasErrors('name');
        $post(['starts_at' => 'kemarin'])->assertSessionHasErrors('starts_at');

        $this->assertSame(0, FlashSale::count());
    }

    public function test_a_product_cannot_be_in_two_overlapping_active_campaigns(): void
    {
        $p = $this->product();
        $admin = $this->userAs('admin');
        $this->actingAs($admin)->post(route('admin.flash-sales.store'), $this->payload($p))->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.flash-sales.store'), $this->payload($p, ['name' => 'Bentrok']))->assertSessionHasErrors('items.0.product_id');

        // jadwal tidak bertumpuk: boleh
        $this->actingAs($admin)->post(route('admin.flash-sales.store'), $this->payload($p, ['name' => 'Besok', 'starts_at' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addDays(2)->addHours(3)->format('Y-m-d\TH:i')]))->assertSessionHasNoErrors();
        $this->assertSame(2, FlashSale::count());
    }

    public function test_update_cannot_cut_quota_below_sold_or_remove_sold_products_and_delete_is_blocked_after_sales(): void
    {
        $p = $this->product();
        $q = $this->product();
        $admin = $this->userAs('admin');
        $s = FlashSale::create(['name' => 'Promo', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(3), 'is_active' => true]);
        $i = $s->items()->create(['product_id' => $p->id, 'sale_price' => 800000, 'quota' => 10]);
        $this->boughtOrder($i, $p, 4);
        $base = ['name' => 'Promo', 'starts_at' => now('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addHours(3)->format('Y-m-d\TH:i'), 'is_active' => '1'];

        $this->actingAs($admin)->put(route('admin.flash-sales.update', $s->id), $base + ['items' => [['product_id' => $p->id, 'sale_price' => 800000, 'quota' => 3]]])->assertSessionHasErrors('items.0.quota');
        $this->actingAs($admin)->put(route('admin.flash-sales.update', $s->id), $base + ['items' => [['product_id' => $q->id, 'sale_price' => 400000, 'quota' => 3]]])->assertSessionHasErrors('items');
        $this->assertSame(1, $s->items()->count());

        $this->actingAs($admin)->put(route('admin.flash-sales.update', $s->id), $base + ['items' => [['product_id' => $p->id, 'sale_price' => 790000, 'quota' => 6], ['product_id' => $q->id, 'sale_price' => 400000, 'quota' => 3]]])->assertSessionHasNoErrors();
        $this->assertSame(2, $s->items()->count());
        $this->assertEquals(790000, $s->items()->where('product_id', $p->id)->first()->sale_price);

        $this->actingAs($admin)->delete(route('admin.flash-sales.destroy', $s->id))->assertSessionHas('error');
        $this->assertNotNull(FlashSale::find($s->id));

        $empty = FlashSale::create(['name' => 'Kosong', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2), 'is_active' => true]);
        $this->actingAs($admin)->delete(route('admin.flash-sales.destroy', $empty->id))->assertSessionHas('success');
        $this->assertNull(FlashSale::find($empty->id));
    }

    public function test_permissions_staff_can_only_view_and_others_are_blocked(): void
    {
        $p = $this->product();
        $staff = $this->userAs('staff');

        $this->actingAs($staff)->get(route('admin.flash-sales.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.flash-sales.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.flash-sales.store'), $this->payload($p))->assertForbidden();

        foreach (['gudang', 'keuangan', 'customer'] as $role) {
            $this->actingAs($this->userAs($role))->get(route('admin.flash-sales.index'))->assertForbidden();
        }
        $this->assertSame(0, FlashSale::count());
        $this->actingAs($this->userAs('admin'))->get(route('admin.flash-sales.create'))->assertOk()->assertSee('Produk promo');
    }

    public function test_deactivating_a_campaign_ends_the_promo_immediately(): void
    {
        $p = $this->product();
        [$s] = $this->sale($p, 750000, 5);
        $this->assertEquals(750000, $p->currentPrice());

        $this->actingAs($this->userAs('admin'))->put(route('admin.flash-sales.update', $s->id), [
            'name' => 'Promo Uji', 'starts_at' => now('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addHours(3)->format('Y-m-d\TH:i'), 'is_active' => '0',
            'items' => [['product_id' => $p->id, 'sale_price' => 750000, 'quota' => 5]],
        ])->assertSessionHasNoErrors();

        app(FlashSaleService::class)->flush();
        $this->assertEquals(1000000, $p->fresh()->currentPrice());
    }
}
