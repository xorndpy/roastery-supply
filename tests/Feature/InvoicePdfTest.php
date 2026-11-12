<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Invoice PDF yang bisa dikustom lewat Pengaturan, favicon, dan helper gambar untuk DomPDF. */
class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Setting::forgetMemo();
        Cache::flush();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function image(string $format, int $w = 300, int $h = 120): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 100, 50));
        ob_start();
        match ($format) {
            'png' => imagepng($im),
            'jpg' => imagejpeg($im),
            'webp' => imagewebp($im),
        };

        return (string) ob_get_clean();
    }

    private function order(bool $paid = true): Order
    {
        $u = User::factory()->create(['name' => 'Danang']);
        $c = Customer::unguarded(fn () => Customer::create(['user_id' => $u->id, 'name' => 'Danang', 'email' => $u->email]));
        static $n = 0;
        $n++;
        $p = Product::unguarded(fn () => Product::create(['name' => "Mesin Uji {$n}", 'slug' => "mesin-uji-{$n}", 'sku' => "U-{$n}", 'price' => 5000000, 'stock' => 5, 'stock_threshold' => 1, 'is_active' => true]));
        $o = Order::unguarded(fn () => Order::create([
            'order_number' => Order::generateOrderNumber(), 'customer_id' => $c->id, 'user_id' => $u->id, 'status' => $paid ? 'selesai' : 'menunggu_pembayaran',
            'subtotal' => 5000000, 'shipping_cost' => 20000, 'total' => 5020000, 'shipping_name' => 'Danang', 'shipping_address' => 'Jl. Ancol',
        ]));
        OrderItem::unguarded(fn () => OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_name' => $p->name, 'product_sku' => $p->sku, 'price' => 5000000, 'quantity' => 1, 'subtotal' => 5000000]));
        if ($paid) {
            Payment::unguarded(fn () => Payment::create(['order_id' => $o->id, 'method' => 'manual', 'amount' => 5020000, 'status' => 'verified']));
        }

        return $o->load(['customer', 'items', 'payment']);
    }

    // ---------- helper gambar ----------

    public function test_image_helper_embeds_png_and_jpeg_as_is(): void
    {
        Storage::disk('public')->put('s/a.png', $this->image('png', 200, 80));
        Storage::disk('public')->put('s/b.jpg', $this->image('jpg', 200, 80));

        $this->assertStringStartsWith('data:image/png;base64,', pdf_image_data_uri('s/a.png'));
        $this->assertStringStartsWith('data:image/jpeg;base64,', pdf_image_data_uri('/s/b.jpg'));
    }

    public function test_image_helper_converts_webp_and_shrinks_large_images(): void
    {
        Storage::disk('public')->put('s/w.webp', $this->image('webp', 300, 100));
        $webp = pdf_image_data_uri('s/w.webp');
        $this->assertStringStartsWith('data:image/png;base64,', $webp); // DomPDF tidak mendukung WEBP

        Storage::disk('public')->put('s/big.png', $this->image('png', 1600, 400));
        $big = pdf_image_data_uri('s/big.png', 520);
        $size = getimagesizefromstring(base64_decode(substr($big, strlen('data:image/png;base64,'))));
        $this->assertSame(520, $size[0]);
    }

    public function test_image_helper_returns_null_for_missing_or_non_image_files(): void
    {
        Storage::disk('public')->put('s/not-image.png', 'ini bukan gambar');

        $this->assertNull(pdf_image_data_uri(null));
        $this->assertNull(pdf_image_data_uri(''));
        $this->assertNull(pdf_image_data_uri('s/tidak-ada.png'));
        $this->assertNull(pdf_image_data_uri('s/not-image.png'));
    }

    // ---------- tampilan invoice ----------

    public function test_invoice_uses_settings_for_branding_and_text(): void
    {
        Storage::disk('public')->put('settings/logo.png', $this->image('png'));
        foreach (['site_name' => 'KOFFLINE', 'tagline' => 'Kualitas yang Bekerja di Balik Rasa.', 'site_logo' => 'settings/logo.png',
            'contact_email' => 'admin@koffline.my.id', 'social_instagram' => 'https://instagram.com/koffline',
            'invoice_thanks' => 'Pesan penutup kustom.', 'invoice_slogan' => 'Brew better. Live better.'] as $k => $v) {
            Setting::set($k, $v);
        }

        $html = view('pdf.invoice', ['order' => $this->order()])->render();

        $this->assertStringContainsString('LUNAS', $html);
        $this->assertStringContainsString('Kualitas yang Bekerja di Balik Rasa.', $html);
        $this->assertStringContainsString('Pesan penutup kustom.', $html);
        $this->assertStringContainsString('Brew better. Live better.', $html);
        $this->assertStringContainsString('@koffline', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);          // logo tertanam, bukan URL
        $this->assertStringNotContainsString('http://localhost/storage', $html);
    }

    public function test_invoice_logo_can_be_overridden_and_falls_back_to_text_without_logo(): void
    {
        Setting::set('site_name', 'KOFFLINE');
        $html = view('pdf.invoice', ['order' => $this->order()])->render();
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringContainsString('KOFFLINE', $html); // nama usaha sebagai pengganti logo

        Storage::disk('public')->put('settings/main.png', $this->image('png', 100, 100));
        Storage::disk('public')->put('settings/inv.jpg', $this->image('jpg', 100, 100));
        Setting::set('site_logo', 'settings/main.png');
        Setting::set('invoice_logo', 'settings/inv.jpg');
        $this->assertStringContainsString('data:image/jpeg;base64,', view('pdf.invoice', ['order' => $this->order()])->render());
    }

    public function test_unpaid_invoice_shows_status_and_bank_details(): void
    {
        Setting::set('bank_name', 'BCA');
        Setting::set('bank_account_number', '1234567890');
        Setting::set('bank_account_name', 'PT Contoh');

        $html = view('pdf.invoice', ['order' => $this->order(paid: false)])->render();

        $this->assertStringContainsString('BELUM DIBAYAR', $html);
        $this->assertStringNotContainsString('LUNAS', $html);
        $this->assertStringContainsString('1234567890', $html);

        $paidHtml = view('pdf.invoice', ['order' => $this->order()])->render();
        $this->assertStringNotContainsString('1234567890', $paidHtml); // rekening hanya untuk yang belum bayar
    }

    public function test_admin_downloads_a_real_pdf(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $order = $this->order();

        $res = $this->actingAs($admin)->get(route('admin.orders.invoice', $order->id));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_invoice_text_is_escaped(): void
    {
        Setting::set('invoice_thanks', '<script>alert(1)</script>');

        $html = view('pdf.invoice', ['order' => $this->order()])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ---------- favicon ----------

    public function test_default_favicon_files_exist_and_are_real_images(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'favicon-32.png', 'apple-touch-icon.png'] as $f) {
            $this->assertGreaterThan(100, filesize(public_path($f)), "{$f} tidak boleh kosong");
        }
    }

    public function test_pages_link_the_default_favicon_and_prefer_the_uploaded_one(): void
    {
        $this->get('/')->assertOk()->assertSee('favicon.svg', false)->assertSee('favicon.ico', false);

        Setting::set('site_favicon', 'settings/fav.png');
        $this->get('/')->assertOk()->assertSee('settings/fav.png', false)->assertDontSee('favicon.svg', false);
    }

    public function test_admin_and_login_pages_have_a_favicon_too(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('favicon.svg', false);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('favicon.svg', false);
    }
}
