<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeContentTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, array $extra = []): Category
    {
        return Category::unguarded(fn () => Category::create(array_merge(['name' => $name, 'slug' => str($name)->slug(), 'is_active' => true], $extra)));
    }

    private function product(Category $c, string $name): Product
    {
        static $n = 0;
        $n++;

        return Product::unguarded(fn () => Product::create(['name' => $name, 'slug' => "p-$n", 'sku' => "SK-$n", 'price' => 1000, 'stock' => 5, 'category_id' => $c->id, 'is_active' => true]));
    }

    private function testimonial(string $name, string $text, int $rating = 5, array $extra = []): Testimonial
    {
        return Testimonial::unguarded(fn () => Testimonial::create(array_merge(['customer_name' => $name, 'content' => $text, 'rating' => $rating, 'is_approved' => true], $extra)));
    }

    private function setting(string $key, string $value): void
    {
        DB::table('settings')->insert(['key' => $key, 'value' => $value, 'group' => 'general', 'created_at' => now(), 'updated_at' => now()]);
        \App\Models\Setting::forgetMemo();
    }

    // ---------------------------------------------------------------- kategori

    public function test_home_hides_empty_categories_counts_subcategory_products_and_shows_photo(): void
    {
        $parent = $this->category('Sparepart');
        $child = $this->category('Grinder', ['parent_id' => $parent->id]);
        $this->category('Kosong');                                         // tanpa produk
        $with = $this->category('Espresso Machine', ['image' => 'cat/espresso.jpg']);
        $this->product($child, 'Grinder A');
        $this->product($with, 'Mesin A');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('>Kosong<', $html, 'kategori tanpa produk tidak ditampilkan');
        $this->assertStringContainsString('Sparepart', $html);                 // induk memuat produk sub-kategorinya
        $this->assertStringContainsString('storage/cat/espresso.jpg', $html);   // foto kategori dipakai
    }

    public function test_catalog_parent_category_includes_subcategory_products(): void
    {
        $parent = $this->category('Sparepart');
        $child = $this->category('Grinder', ['parent_id' => $parent->id]);
        $this->product($child, 'Grinder Khusus');

        $this->get(route('products.index', ['category' => 'sparepart']))->assertOk()->assertSee('Grinder Khusus');
    }

    // ---------------------------------------------------------------- testimoni

    public function test_home_testimonials_are_curated_not_just_latest(): void
    {
        $this->testimonial('Siti', 'Baik dan mudah dipahami');
        $this->testimonial('SETYA', 'BAIK dan mudah dipahami');             // isi sama (beda huruf besar)
        $this->testimonial('Retno', 'Bik dan mudah dipahami');              // salah ketik, hampir sama
        $this->testimonial('Siti', 'Pengiriman cepat dan rapi sekali.');     // nama sama
        $this->testimonial('Joko', 'Kurang memuaskan sama sekali.', 2);      // rating rendah
        $this->testimonial('Dewi', 'Teknisi datang tepat waktu dan sangat membantu.');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'dan mudah dipahami') , 'hanya satu dari ulasan yang (hampir) sama');
        $this->assertStringNotContainsString('Kurang memuaskan', $html);
        $this->assertStringContainsString('Teknisi datang tepat waktu', $html);
    }

    public function test_pinned_testimonials_come_first_and_new_reviews_do_not_push_them_out(): void
    {
        $this->testimonial('Lama', 'Ulasan lama yang sangat bagus dan detail.', 5, ['is_featured' => true, 'created_at' => now()->subYear()]);
        for ($i = 1; $i <= 10; $i++) {
            $this->testimonial("Baru $i", "Ulasan baru nomor $i yang cukup panjang.", 5);
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Ulasan lama yang sangat bagus', $html, 'yang disematkan tidak tergeser ulasan baru');
        $this->assertLessThanOrEqual(6, substr_count($html, '<figure'));
    }

    public function test_admin_can_pin_up_to_six_and_only_approved_ones(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $pending = $this->testimonial('X', 'Belum disetujui admin.', 5, ['is_approved' => false]);
        $this->actingAs($admin)->patch(route('admin.testimonials.toggle-featured', $pending->id))->assertSessionHas('error');
        $this->assertFalse($pending->fresh()->is_featured);

        $list = [];
        for ($i = 1; $i <= 7; $i++) {
            $list[] = $this->testimonial("N$i", "Ulasan nomor $i yang cukup panjang.");
        }
        foreach (array_slice($list, 0, 6) as $t) {
            $this->actingAs($admin)->patch(route('admin.testimonials.toggle-featured', $t->id))->assertSessionHas('success');
        }
        $this->actingAs($admin)->patch(route('admin.testimonials.toggle-featured', $list[6]->id))->assertSessionHas('error');
        $this->assertSame(6, Testimonial::where('is_featured', true)->count());

        // menyembunyikan ulasan otomatis melepas sematannya
        $this->actingAs($admin)->patch(route('admin.testimonials.toggle-approval', $list[0]->id));
        $this->assertFalse($list[0]->fresh()->is_featured);

        $this->actingAs($admin)->get(route('admin.testimonials.index'))->assertOk()->assertSee('Disematkan saat ini');
    }

    // ---------------------------------------------------------------- header halaman

    public function test_page_header_uses_page_photo_then_shared_photo_and_stays_plain_on_transaction_pages(): void
    {
        $this->setting('header_bg', 'umum/umum.jpg');
        $this->setting('header_bg_katalog', 'khusus/katalog.jpg');

        $this->get(route('products.index'))->assertOk()->assertSee('storage/khusus/katalog.jpg')->assertDontSee('storage/umum/umum.jpg');
        $this->get(route('faq'))->assertOk()->assertSee('storage/umum/umum.jpg');                 // cadangan: foto umum
        $this->get(route('customer.cart.index'))->assertOk()->assertDontSee('storage/umum/umum.jpg');       // transaksi: polos
    }
}
