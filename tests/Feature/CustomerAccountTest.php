<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesRegions;
use Tests\TestCase;

/** Halaman Akun Saya (tindakan berikutnya, voucher, pesanan), voucher di checkout, dan foto profil. */
class CustomerAccountTest extends TestCase
{
    use RefreshDatabase, MakesRegions;

    private function product(int $price = 1000000): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => "Grinder G{$n}", 'slug' => "grinder-g{$n}", 'sku' => "G-{$n}", 'price' => $price, 'stock' => 10, 'stock_threshold' => 1, 'is_active' => true]));
    }

    private function order(User $user, string $status = 'menunggu_bayar', ?Product $p = null, int $items = 1): Order
    {
        $customer = Customer::firstOrCreate(['user_id' => $user->id], ['name' => 'B', 'email' => $user->email]);
        $order = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => $status,
            'subtotal' => 1000000, 'discount' => 0, 'total' => 1000000,
        ]));
        for ($i = 0; $i < $items; $i++) {
            $prod = $p ?? $this->product();
            OrderItem::unguarded(fn () => OrderItem::create([
                'order_id' => $order->id, 'product_id' => $prod->id, 'product_name' => $prod->name, 'product_sku' => $prod->sku,
                'price' => 1000000, 'cost_price' => 0, 'quantity' => 1, 'subtotal' => 1000000,
            ]));
        }

        return $order;
    }

    private function voucher(array $a = []): Voucher
    {
        static $n = 0;
        $n++;

        return Voucher::create(array_merge([
            'code' => "ACC{$n}", 'type' => 'percentage', 'value' => 10, 'min_purchase' => 0, 'quota' => 0, 'per_user_limit' => 1, 'is_active' => true,
        ], $a));
    }

    // ---------- Akun Saya ----------

    public function test_dashboard_prompts_to_pay_pending_order(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, 'menunggu_bayar');

        $this->actingAs($user)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Menunggu pembayaran')
            ->assertSee('Bayar sekarang')
            ->assertSee($order->order_number);
    }

    public function test_dashboard_says_payment_is_being_checked_when_proof_was_sent(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, 'menunggu_bayar');
        $order->payments()->create(['amount' => 1000000, 'method' => 'transfer', 'status' => 'pending']);

        $this->actingAs($user)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Pembayaran sedang diperiksa')
            ->assertDontSee('Bayar sekarang');
    }

    public function test_dashboard_offers_tracking_for_shipped_order(): void
    {
        $user = User::factory()->create();
        $this->order($user, 'dikirim');

        $this->actingAs($user)->get(route('customer.dashboard'))->assertOk()->assertSee('Lacak pesanan')->assertDontSee('Bayar sekarang');
    }

    public function test_dashboard_without_action_hides_the_action_card(): void
    {
        $user = User::factory()->create();
        $this->order($user, 'selesai');

        $this->actingAs($user)->get(route('customer.dashboard'))->assertOk()->assertDontSee('Bayar sekarang')->assertDontSee('Lacak pesanan');
    }

    public function test_recent_orders_show_product_names_not_only_codes(): void
    {
        $user = User::factory()->create();
        $p = $this->product();
        $this->order($user, 'selesai', $p, 3);

        $this->actingAs($user)->get(route('customer.dashboard'))->assertOk()->assertSee($p->name)->assertSee('dan 2 produk lain');
    }

    public function test_empty_dashboard_invites_to_shop(): void
    {
        $this->actingAs(User::factory()->create())->get(route('customer.dashboard'))->assertOk()->assertSee('Belum ada pesanan')->assertSee('Mulai Belanja');
    }

    public function test_new_member_sees_welcome_voucher_but_veteran_does_not(): void
    {
        $this->voucher(['code' => 'WELCOME10', 'audience' => 'new_member', 'description' => 'Hadiah pelanggan baru']);
        $veteran = User::factory()->create();
        $this->order($veteran, 'selesai');

        $this->actingAs(User::factory()->create())->get(route('customer.dashboard'))
            ->assertOk()->assertSee('Selamat datang')->assertSee('WELCOME10')->assertSee('Salin kode');

        $this->actingAs($veteran)->get(route('customer.dashboard'))->assertOk()->assertDontSee('WELCOME10');
    }

    public function test_secret_voucher_is_not_listed(): void
    {
        $this->voucher(['code' => 'RAHASIA1', 'is_public' => false]);

        $this->actingAs(User::factory()->create())->get(route('customer.dashboard'))->assertOk()->assertDontSee('RAHASIA1');
    }

    public function test_dashboard_requires_login(): void
    {
        $this->get(route('customer.dashboard'))->assertRedirect();
    }

    // ---------- Checkout ----------

    private function visitCheckout(User $user, Product $p)
    {
        return $this->actingAs($user)->withSession(['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => 1, 'image' => null, 'stock' => $p->stock]]])
            ->get(route('customer.checkout.index'));
    }

    public function test_checkout_lists_vouchers_and_shows_gap_for_unreachable_ones(): void
    {
        $this->voucher(['code' => 'PASTI10', 'min_purchase' => 500000]);
        $this->voucher(['code' => 'JAUH50', 'min_purchase' => 5000000]);

        $this->visitCheckout(User::factory()->create(), $this->product(1000000))
            ->assertOk()->assertSee('Voucher untuk kamu')->assertSee('PASTI10')->assertSee('Belanja Rp 4.000.000 lagi');
    }

    public function test_picking_a_listed_voucher_applies_it(): void
    {
        $v = $this->voucher(['code' => 'PILIH10']);
        $user = User::factory()->create();
        $p = $this->product();

        $this->actingAs($user)->withSession(['cart' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'price' => $p->price, 'quantity' => 1, 'image' => null, 'stock' => $p->stock]]])
            ->post(route('customer.checkout.voucher'), ['code' => $v->code])->assertRedirect(route('customer.checkout.index'));

        $this->assertSame('PILIH10', session('checkout_voucher'));
    }

    // ---------- Foto profil ----------

    private function profilePayload(User $user, array $extra = []): array
    {
        return array_merge(['name' => $user->name, 'email' => $user->email], $extra);
    }

    public function test_customer_can_upload_avatar_and_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
            'cover' => UploadedFile::fake()->image('cover.png', 1600, 500),
        ]))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertNotNull($user->cover);
        Storage::disk('public')->assertExists($user->avatar);
        Storage::disk('public')->assertExists($user->cover);
    }

    public function test_replacing_and_removing_avatar_deletes_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['avatar' => UploadedFile::fake()->image('a.jpg')]));
        $first = $user->fresh()->avatar;

        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['avatar' => UploadedFile::fake()->image('b.jpg')]));
        $second = $user->fresh()->avatar;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['remove_avatar' => 1]));
        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_photo_is_optional_and_untouched_when_not_sent(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => 'avatars/lama.jpg']);

        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['name' => 'Nama Baru']))->assertSessionHasNoErrors();

        $this->assertSame('avatars/lama.jpg', $user->fresh()->avatar);
        $this->assertSame('Nama Baru', $user->fresh()->name);
    }

    public function test_google_avatar_url_is_kept_off_disk_logic_and_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar' => 'https://lh3.googleusercontent.com/a/foto']);

        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['remove_avatar' => 1]))->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_rejects_non_images_and_oversize_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['avatar' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('avatar');
        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['avatar' => UploadedFile::fake()->image('besar.jpg')->size(3000)]))
            ->assertSessionHasErrors('avatar');
        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['cover' => UploadedFile::fake()->image('besar.jpg')->size(5000)]))
            ->assertSessionHasErrors('cover');

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_profile_page_has_photo_fields_and_dashboard_uses_cover(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('customer.profile.edit'))->assertOk()->assertSee('name="avatar"', false)->assertSee('multipart/form-data', false);

        $this->actingAs($user)->patch(route('customer.profile.update'), $this->profilePayload($user, ['cover' => UploadedFile::fake()->image('c.jpg', 1600, 500)]));
        $this->actingAs($user->fresh())->get(route('customer.dashboard'))->assertOk()->assertSee($user->fresh()->cover_url, false);
    }
}
