<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use App\Models\Branch;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Setting::forgetMemo();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function userAs(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    // ================= PENGATURAN =================

    public function test_settings_are_saved_and_reflected_on_the_public_site(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'site_name' => 'Kopi Mesin Jaya', 'contact_email' => 'halo@kopimesin.test', 'contact_phone' => '021-555',
            'contact_whatsapp' => '628123456789', 'social_instagram' => 'https://instagram.com/kopimesin',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('Kopi Mesin Jaya', Setting::get('site_name'));

        $this->get('/')->assertOk()->assertSee('Kopi Mesin Jaya')->assertSee('halo@kopimesin.test')
            ->assertSee('https://wa.me/628123456789', false)->assertSee('https://instagram.com/kopimesin', false);
    }

    public function test_footer_shows_no_made_up_contact_details_by_default(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('hello@roastery.id')->assertDontSee('+62 21 1234 5678')->assertDontSee('Jakarta, Indonesia');
    }

    public function test_setting_defaults_apply_until_overridden(): void
    {
        $this->assertSame('Roastery Supply', Setting::get('site_name'));
        Setting::set('site_name', 'Baru');
        $this->assertSame('Baru', Setting::get('site_name'));
        Setting::set('site_name', null);
        $this->assertSame('Roastery Supply', Setting::get('site_name')); // kosong -> default lagi
    }

    public function test_settings_validate_urls_and_whatsapp(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['social_instagram' => 'javascript:alert(1)'])->assertSessionHasErrors('social_instagram');
        $this->actingAs($admin)->put(route('admin.settings.update'), ['contact_whatsapp' => '+62 812-xx'])->assertSessionHasErrors('contact_whatsapp');
        $this->actingAs($admin)->put(route('admin.settings.update'), ['contact_email' => 'bukan-email'])->assertSessionHasErrors('contact_email');
    }

    public function test_logo_upload_and_removal(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['site_logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHasNoErrors();
        $path = Setting::get('site_logo');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->get('/')->assertSee('storage/' . $path, false);

        $this->actingAs($admin)->put(route('admin.settings.update'), ['remove_site_logo' => '1']);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull(Setting::stored()['site_logo']);
    }

    public function test_settings_access_and_activity_log(): void
    {
        $this->actingAs($this->userAs('staff'))->get(route('admin.settings.index'))->assertForbidden();
        $admin = $this->userAs('admin');
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Rekening Pembayaran');

        $this->actingAs($admin)->put(route('admin.settings.update'), ['tagline' => 'Tagline baru']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'pengaturan', 'causer_id' => $admin->id]);
    }

    public function test_bank_account_on_order_page_comes_from_settings_not_hardcode(): void
    {
        $user = User::factory()->create();
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => 'B', 'email' => $user->email]));
        $order = Order::unguarded(fn () => Order::create(['order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => 'menunggu_bayar', 'subtotal' => 1, 'total' => 1]));

        $this->actingAs($user)->get(route('customer.orders.show', $order->order_number))
            ->assertOk()->assertSee('belum diatur')->assertDontSee('1234567890');

        Setting::set('bank_name', 'Bank Mandiri');
        Setting::set('bank_account_number', '9988776655');
        Setting::set('bank_account_name', 'PT Kopi Jaya');

        $this->actingAs($user)->get(route('customer.orders.show', $order->order_number))
            ->assertOk()->assertSee('9988776655')->assertSee('PT Kopi Jaya')->assertDontSee('belum diatur');
    }

    public function test_old_seeder_placeholders_are_cleaned_but_admin_edits_survive(): void
    {
        Setting::set('contact_phone', '+62 812-3456-7890');           // sisa seeder lama
        Setting::set('contact_email', 'admin-asli@kopi.test');        // sudah diubah admin
        Setting::set('site_logo', 'assets/images/logo.png');          // file tidak ada

        (require database_path('migrations/2026_10_04_000002_remove_placeholder_settings.php'))->up();
        Setting::forgetMemo();

        $this->assertNull(Setting::stored()['contact_phone'] ?? null);
        $this->assertNull(Setting::stored()['site_logo'] ?? null);
        $this->assertSame('admin-asli@kopi.test', Setting::get('contact_email'));
    }

    public function test_seeders_do_not_overwrite_admin_content_and_are_repeatable(): void
    {
        $this->seed(\Database\Seeders\SettingSeeder::class);
        Setting::set('site_name', 'Nama Pilihan Admin');
        $this->seed(\Database\Seeders\SettingSeeder::class);
        $this->assertSame('Nama Pilihan Admin', Setting::get('site_name'));

        $this->seed(\Database\Seeders\FaqSeeder::class);
        $count = Faq::count();
        $this->assertGreaterThan(0, $count);
        $this->seed(\Database\Seeders\FaqSeeder::class);
        $this->assertSame($count, Faq::count());
        $this->assertNull(Setting::stored()['contact_phone'] ?? null); // kontak tidak di-seed
    }

    // ================= BANNER =================

    private function banner(array $attrs = []): Banner
    {
        static $n = 0;
        $n++;

        return Banner::create(array_merge(['title' => "Banner {$n}", 'image' => "banners/b{$n}.jpg", 'sort_order' => $n, 'is_active' => true], $attrs));
    }

    public function test_banners_follow_their_schedule_on_the_homepage(): void
    {
        $this->banner(['title' => 'TAYANG-SEKARANG']);
        $this->banner(['title' => 'BELUM-MULAI', 'start_at' => now()->addDay()]);
        $this->banner(['title' => 'SUDAH-BERAKHIR', 'end_at' => now()->subDay()]);
        $this->banner(['title' => 'NONAKTIF', 'is_active' => false]);
        $this->banner(['title' => 'DALAM-JADWAL', 'start_at' => now()->subDay(), 'end_at' => now()->addDay()]);

        $this->get('/')->assertOk()->assertSee('TAYANG-SEKARANG')->assertSee('DALAM-JADWAL')
            ->assertDontSee('BELUM-MULAI')->assertDontSee('SUDAH-BERAKHIR')->assertDontSee('NONAKTIF');
    }

    public function test_banner_crud_validation_and_unsafe_links(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('admin.banners.store'), [
            'title' => 'Promo', 'image' => UploadedFile::fake()->image('b.jpg', 1200, 500), 'link' => '/katalog', 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index'));
        $b = Banner::first();
        Storage::disk('public')->assertExists($b->image);

        // link berbahaya & jadwal terbalik ditolak
        $this->actingAs($admin)->post(route('admin.banners.store'), ['title' => 'X', 'image' => UploadedFile::fake()->image('c.jpg'), 'link' => 'javascript:alert(1)'])->assertSessionHasErrors('link');
        $this->actingAs($admin)->post(route('admin.banners.store'), ['title' => 'X', 'image' => UploadedFile::fake()->image('c.jpg'), 'start_at' => '2026-12-10 10:00', 'end_at' => '2026-12-01 10:00'])->assertSessionHasErrors('end_at');
        $this->actingAs($admin)->post(route('admin.banners.store'), ['title' => 'X'])->assertSessionHasErrors('image');

        // ganti gambar -> file lama dihapus
        $old = $b->image;
        $this->actingAs($admin)->put(route('admin.banners.update', $b->id), ['title' => 'Promo 2', 'image' => UploadedFile::fake()->image('d.jpg')])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);

        $this->actingAs($admin)->delete(route('admin.banners.destroy', $b->id));
        $this->assertSoftDeleted($b);
    }

    public function test_banner_drag_drop_reorder(): void
    {
        $a = $this->banner(['sort_order' => 1]);
        $b = $this->banner(['sort_order' => 2]);
        $c = $this->banner(['sort_order' => 3]);

        $this->actingAs($this->userAs('admin'))->postJson(route('admin.banners.reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertOk();

        $this->assertSame([$c->id, $a->id, $b->id], Banner::orderBy('sort_order')->pluck('id')->all());
        $this->actingAs($this->userAs('staff'))->postJson(route('admin.banners.reorder'), ['ids' => [$a->id]])->assertForbidden();
    }

    // ================= TESTIMONI =================

    private function completedOrderFor(User $user, string $status = 'selesai'): Order
    {
        $customer = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]));

        return Order::unguarded(fn () => Order::create(['order_number' => Order::generateOrderNumber(), 'customer_id' => $customer->id, 'user_id' => $user->id, 'status' => $status, 'subtotal' => 1, 'total' => 1]));
    }

    public function test_customer_testimonial_needs_approval_before_it_is_public(): void
    {
        $user = User::factory()->create();
        $order = $this->completedOrderFor($user);

        $this->actingAs($user)->post(route('customer.orders.testimonial', $order->order_number), ['rating' => 5, 'content' => 'Mesinnya mantap, pengiriman rapi.'])->assertSessionHas('success');

        $t = Testimonial::first();
        $this->assertFalse($t->is_approved);
        $this->assertSame($order->id, $t->order_id);
        $this->get('/')->assertDontSee('Mesinnya mantap');

        $admin = $this->userAs('admin');
        $this->actingAs($admin)->patch(route('admin.testimonials.toggle-approval', $t->id))->assertSessionHas('success');
        $this->get('/')->assertSee('Mesinnya mantap');

        $this->actingAs($admin)->patch(route('admin.testimonials.toggle-approval', $t->id));
        $this->get('/')->assertDontSee('Mesinnya mantap'); // disembunyikan lagi
    }

    public function test_testimonial_only_for_completed_own_order_and_only_once(): void
    {
        $user = User::factory()->create();
        $notDone = $this->completedOrderFor($user, 'dikirim');
        $payload = ['rating' => 4, 'content' => 'Sudah sampai dengan baik.'];

        $this->actingAs($user)->post(route('customer.orders.testimonial', $notDone->order_number), $payload)->assertSessionHas('error');
        $this->assertSame(0, Testimonial::count());

        $done = $this->completedOrderFor($user);
        $this->actingAs($user)->post(route('customer.orders.testimonial', $done->order_number), $payload)->assertSessionHas('success');
        $this->actingAs($user)->post(route('customer.orders.testimonial', $done->order_number), $payload)->assertSessionHas('error');
        $this->assertSame(1, Testimonial::count());

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('customer.orders.testimonial', $done->order_number), $payload)->assertNotFound();

        $this->actingAs($user)->post(route('customer.orders.testimonial', $this->completedOrderFor($user)->order_number), ['rating' => 9, 'content' => 'x'])->assertSessionHasErrors(['rating', 'content']);
    }

    public function test_testimonial_text_is_escaped_and_admin_can_add_manually(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('admin.testimonials.store'), [
            'customer_name' => 'Kedai <b>Senja</b>', 'content' => '<script>alert("xss")</script> mesinnya bagus', 'rating' => 5, 'is_approved' => 1,
        ])->assertSessionHasNoErrors();

        $this->get('/')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("xss")', false)->assertSee('Kedai &lt;b&gt;Senja&lt;/b&gt;', false);
        $this->actingAs($admin)->get(route('admin.testimonials.index', ['status' => 'approved']))->assertOk();
        $this->actingAs($this->userAs('staff'))->get(route('admin.testimonials.index'))->assertForbidden();
    }

    // ================= FAQ =================

    public function test_faq_crud_and_public_grouping(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('admin.faqs.store'), ['category' => 'Garansi', 'question' => 'Berapa lama garansi?', 'answer' => "Satu tahun.\nBaris kedua <i>x</i>", 'sort_order' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.faqs.store'), ['category' => 'Garansi', 'question' => 'TERSEMBUNYI', 'answer' => 'x', 'sort_order' => 2])->assertSessionHasNoErrors(); // is_active tidak dicentang

        $this->get(route('faq'))->assertOk()->assertSee('Garansi')->assertSee('Berapa lama garansi?')
            ->assertDontSee('TERSEMBUNYI')->assertSee('&lt;i&gt;x&lt;/i&gt;', false);

        $faq = Faq::where('question', 'Berapa lama garansi?')->first();
        $this->actingAs($admin)->put(route('admin.faqs.update', $faq->id), ['category' => 'Garansi', 'question' => 'Garansi berapa lama?', 'answer' => 'Dua tahun', 'is_active' => 1]);
        $this->assertSame('Garansi berapa lama?', $faq->fresh()->question);
        $this->actingAs($admin)->get(route('admin.faqs.index'))->assertOk()->assertSee('Garansi berapa lama?');

        $this->actingAs($admin)->delete(route('admin.faqs.destroy', $faq->id));
        $this->assertSoftDeleted($faq);
        $this->actingAs($admin)->post(route('admin.faqs.store'), ['category' => '', 'question' => '', 'answer' => ''])->assertSessionHasErrors(['category', 'question', 'answer']);
        $this->actingAs($this->userAs('staff'))->get(route('admin.faqs.index'))->assertForbidden();
    }

    // ================= PARTNER =================

    public function test_partner_logo_link_and_homepage(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('admin.partners.store'), ['name' => 'Mitra Kopi', 'logo' => UploadedFile::fake()->image('l.png'), 'link' => 'https://mitra.test', 'is_active' => 1])->assertSessionHasNoErrors();
        $p = Partner::first();
        Storage::disk('public')->assertExists($p->logo);

        $this->get('/')->assertSee('Mitra Kopi')->assertSee('https://mitra.test', false)->assertSee('noopener', false);

        $this->actingAs($admin)->post(route('admin.partners.store'), ['name' => 'Jahat', 'logo' => UploadedFile::fake()->image('l.png'), 'link' => 'javascript:alert(1)'])->assertSessionHasErrors('link');
        $this->actingAs($admin)->post(route('admin.partners.store'), ['name' => 'Tanpa logo'])->assertSessionHasErrors('logo');

        $this->actingAs($admin)->put(route('admin.partners.update', $p->id), ['name' => 'Mitra', 'is_active' => 0]);
        $this->get('/')->assertDontSee('Mitra Kopi'); // nonaktif -> hilang
    }

    // ================= CABANG =================

    public function test_branch_with_coordinates_shows_a_map_and_without_does_not(): void
    {
        $admin = $this->userAs('admin');

        $this->actingAs($admin)->post(route('admin.branches.store'), ['name' => 'Cabang Senopati', 'address' => 'Jl. Contoh 1', 'phone' => '021-1', 'latitude' => '-6.2297', 'longitude' => '106.8085', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.branches.store'), ['name' => 'Cabang Tanpa Peta', 'address' => 'Jl. Lain 2', 'is_active' => 1])->assertSessionHasNoErrors();

        $res = $this->get(route('branches.index'))->assertOk()->assertSee('Cabang Senopati')->assertSee('Cabang Tanpa Peta');
        $res->assertSee('maps.google.com/maps?q=-6.2297,106.8085', false);
        $this->assertSame(1, substr_count($res->getContent(), '<iframe'));
    }

    public function test_branch_coordinates_are_validated(): void
    {
        $admin = $this->userAs('admin');
        $base = ['name' => 'X', 'address' => 'Y'];

        $this->actingAs($admin)->post(route('admin.branches.store'), $base + ['latitude' => '95', 'longitude' => '106'])->assertSessionHasErrors('latitude');
        $this->actingAs($admin)->post(route('admin.branches.store'), $base + ['latitude' => '-6', 'longitude' => '200'])->assertSessionHasErrors('longitude');
        $this->actingAs($admin)->post(route('admin.branches.store'), $base + ['latitude' => '-6'])->assertSessionHasErrors('longitude');
        $this->actingAs($admin)->post(route('admin.branches.store'), $base + ['maps_url' => 'javascript:x'])->assertSessionHasErrors('maps_url');
        $this->assertSame(0, Branch::count());
    }

    // ================= PESAN MASUK =================

    private function message(array $a = []): ContactMessage
    {
        return ContactMessage::create(array_merge(['name' => 'Budi', 'email' => 'budi@test.com', 'subject' => 'Tanya mesin', 'message' => 'Ada stok grinder?'], $a));
    }

    public function test_contact_form_stores_message_and_honeypot_discards_bots(): void
    {
        $data = ['name' => 'Sari', 'email' => 's@test.com', 'subject' => 'Halo', 'message' => 'Mau tanya harga.'];

        $this->post(route('contact.send'), $data)->assertSessionHas('success');
        $this->assertSame(1, ContactMessage::count());
        $this->assertFalse((bool) ContactMessage::first()->is_read);

        $this->post(route('contact.send'), $data + ['website' => 'http://spam.test'])->assertSessionHas('success');
        $this->assertSame(1, ContactMessage::count()); // bot: tampak sukses tapi tidak tersimpan

        $this->post(route('contact.send'), ['name' => '', 'email' => 'x'])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->get(route('contact.index'))->assertOk()->assertSee('name="website"', false);
    }

    public function test_inbox_read_state_reply_and_dashboard_counter(): void
    {
        $staff = $this->userAs('staff');
        $m = $this->message();
        $this->message(['subject' => 'Kedua']);

        $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->assertSee('2')->assertSee('Pesan belum dibaca');
        $this->actingAs($staff)->get(route('admin.messages.index', ['status' => 'unread']))->assertOk()->assertSee('Tanya mesin')->assertSee('Kedua');

        $this->actingAs($staff)->get(route('admin.messages.show', $m->id))->assertOk()->assertSee('Ada stok grinder?')->assertSee('mailto:budi@test.com', false);
        $this->assertTrue((bool) $m->fresh()->is_read);

        $this->actingAs($staff)->patch(route('admin.messages.mark-as-read', $m->id)); // toggle -> belum dibaca
        $this->assertFalse((bool) $m->fresh()->is_read);

        $this->actingAs($staff)->patch(route('admin.messages.mark-replied', $m->id));
        $this->assertNotNull($m->fresh()->replied_at);
        $this->assertTrue((bool) $m->fresh()->is_read);
    }

    public function test_message_text_is_escaped_and_only_admin_can_delete(): void
    {
        $m = $this->message(['message' => '<script>alert(1)</script>']);

        $this->actingAs($this->userAs('staff'))->get(route('admin.messages.show', $m->id))->assertSee('&lt;script&gt;', false);
        $this->actingAs($this->userAs('staff'))->delete(route('admin.messages.destroy', $m->id))->assertForbidden();
        $this->actingAs($this->userAs('admin'))->delete(route('admin.messages.destroy', $m->id))->assertRedirect(route('admin.messages.index'));
        $this->assertSoftDeleted($m);
        $this->actingAs($this->userAs('customer'))->get(route('admin.messages.index'))->assertForbidden();
    }

    // ================= PELANGGAN =================

    private function customerWithOrders(string $name, array $orders, ?string $registered = null): Customer
    {
        $user = User::factory()->create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)) . '@test.com']);
        $c = Customer::unguarded(fn () => Customer::create(['user_id' => $user->id, 'name' => $name, 'email' => $user->email, 'city' => 'Bandung']));
        if ($registered) {
            $c->forceFill(['created_at' => $registered])->save();
        }
        foreach ($orders as [$status, $total, $daysAgo]) {
            $o = Order::unguarded(fn () => Order::create(['order_number' => Order::generateOrderNumber(), 'customer_id' => $c->id, 'user_id' => $user->id, 'status' => $status, 'subtotal' => $total, 'total' => $total]));
            $o->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
        }

        return $c;
    }

    public function test_customer_list_totals_only_count_confirmed_orders(): void
    {
        $c = $this->customerWithOrders('Budi Santoso', [['selesai', 5000000, 10], ['dikirim', 2500000, 5], ['batal', 9000000, 3], ['menunggu_bayar', 7000000, 1]]);

        $res = $this->actingAs($this->userAs('staff'))->get(route('admin.customers.index'))->assertOk()->assertSee('Budi Santoso');
        $res->assertSee('7.500.000')->assertDontSee('16.500.000')->assertDontSee('23.500.000');
        $this->actingAs($this->userAs('staff'))->get(route('admin.customers.index', ['search' => 'tidak-ada']))->assertDontSee('Budi Santoso');
        $this->actingAs($this->userAs('staff'))->get(route('admin.customers.index', ['search' => 'bud']))->assertSee('Budi Santoso');
    }

    public function test_customer_segments(): void
    {
        $staff = $this->userAs('staff');
        $this->customerWithOrders('Aktif Andi', [['selesai', 1000, 20]], '2026-01-01');
        $this->customerWithOrders('Dormant Dedi', [['selesai', 1000, 200]], '2026-01-01');
        $this->customerWithOrders('Baru Bima', [], now()->subDays(5)->toDateTimeString());
        $this->customerWithOrders('Lama Lina', [['batal', 1000, 5]], '2026-01-01');

        $this->actingAs($staff)->get(route('admin.customers.index', ['segment' => 'aktif']))->assertSee('Aktif Andi')->assertDontSee('Dormant Dedi')->assertDontSee('Baru Bima');
        $this->actingAs($staff)->get(route('admin.customers.index', ['segment' => 'dormant']))->assertSee('Dormant Dedi')->assertDontSee('Aktif Andi');
        $this->actingAs($staff)->get(route('admin.customers.index', ['segment' => 'baru']))->assertSee('Baru Bima')->assertDontSee('Dormant Dedi');
        $this->actingAs($staff)->get(route('admin.customers.index', ['segment' => 'belum_beli']))->assertSee('Lama Lina')->assertDontSee('Aktif Andi');
    }

    public function test_customer_detail_shows_history_and_summary(): void
    {
        $c = $this->customerWithOrders('Rina Rahma', [['selesai', 3000000, 10], ['batal', 500000, 2]]);

        $this->actingAs($this->userAs('staff'))->get(route('admin.customers.show', $c->id))->assertOk()
            ->assertSee('Rina Rahma')->assertSee('3.000.000')->assertSee('Riwayat Pesanan')
            ->assertSee($c->orders()->first()->order_number);
    }

    public function test_customer_export_csv_is_admin_only_and_safe(): void
    {
        $this->customerWithOrders('=HYPERLINK("http://jahat.test")', [['selesai', 4000000, 3]]);
        $this->customerWithOrders('Normal Nana', []);

        $this->actingAs($this->userAs('staff'))->get(route('admin.customers.export'))->assertForbidden();

        $res = $this->actingAs($this->userAs('admin'))->get(route('admin.customers.export'));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $csv = $res->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);                 // BOM untuk Excel
        $this->assertStringContainsString('Total Belanja (Rp)', $csv);
        $this->assertStringContainsString('Normal Nana', $csv);
        $this->assertStringContainsString('4000000', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);               // rumus dinetralkan
        $this->assertStringNotContainsString(",\"=HYPERLINK", $csv);

        // mengikuti filter aktif
        $filtered = $this->actingAs($this->userAs('admin'))->get(route('admin.customers.export', ['search' => 'Normal']))->streamedContent();
        $this->assertStringContainsString('Normal Nana', $filtered);
        $this->assertStringNotContainsString('HYPERLINK', $filtered);
    }

    // ================= SMOKE: semua halaman CMS ter-render =================

    public function test_every_cms_admin_page_renders_with_data(): void
    {
        $admin = $this->userAs('admin');
        $banner = $this->banner();
        $faq = Faq::create(['category' => 'Umum', 'question' => 'Q?', 'answer' => 'A', 'sort_order' => 1, 'is_active' => true]);
        $partner = Partner::create(['name' => 'P', 'logo' => 'partners/p.png', 'sort_order' => 1, 'is_active' => true]);
        $branch = Branch::create(['name' => 'B', 'address' => 'Jl. B', 'is_active' => true]);
        $testi = Testimonial::create(['customer_name' => 'T', 'content' => 'Bagus sekali mesinnya', 'rating' => 4, 'is_approved' => false]);
        $msg = $this->message();
        $cust = $this->customerWithOrders('Smoke Test', [['selesai', 1000, 1]]);

        $pages = [
            route('admin.settings.index'),
            route('admin.banners.index'), route('admin.banners.create'), route('admin.banners.edit', $banner->id),
            route('admin.faqs.index'), route('admin.faqs.create'), route('admin.faqs.edit', $faq->id),
            route('admin.partners.index'), route('admin.partners.create'), route('admin.partners.edit', $partner->id),
            route('admin.branches.index'), route('admin.branches.create'), route('admin.branches.edit', $branch->id),
            route('admin.testimonials.index'), route('admin.testimonials.create'), route('admin.testimonials.edit', $testi->id),
            route('admin.messages.index'), route('admin.messages.show', $msg->id),
            route('admin.customers.index'), route('admin.customers.show', $cust->id),
            route('admin.dashboard'),
        ];

        foreach ($pages as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_every_public_page_renders_empty_and_with_content(): void
    {
        $public = [route('home'), route('about'), route('faq'), route('branches.index'), route('contact.index'), route('products.index')];

        foreach ($public as $url) {
            $this->get($url)->assertOk(); // kosong: tidak boleh error
        }

        // beranda dengan produk unggulan + kategori
        $cat = \App\Models\Category::unguarded(fn () => \App\Models\Category::create(['name' => 'Espresso Machine', 'slug' => 'espresso-machine', 'is_active' => true]));
        \App\Models\Product::unguarded(fn () => \App\Models\Product::create([
            'category_id' => $cat->id, 'name' => 'Mesin Unggulan X', 'slug' => 'mesin-unggulan-x', 'sku' => 'U-1',
            'price' => 15000000, 'stock' => 3, 'stock_threshold' => 1, 'is_active' => true, 'is_featured' => true,
        ]));
        Setting::set('about_body', "Paragraf satu.\n\nParagraf dua dengan <b>tag</b>.");
        Setting::set('about_title', 'Cerita Kami');

        $this->get(route('home'))->assertOk()->assertSee('Mesin Unggulan X')->assertSee('Espresso Machine')->assertSee('15.000.000');
        $this->get(route('about'))->assertOk()->assertSee('Cerita Kami')->assertSee('Paragraf satu.')->assertSee('&lt;b&gt;tag&lt;/b&gt;', false);

        $this->seed(\Database\Seeders\FaqSeeder::class);
        $this->get(route('faq'))->assertOk()->assertSee('Bagaimana cara memesan?')->assertSee('Pembayaran');
    }
}
