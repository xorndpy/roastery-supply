<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Milestone;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $o = []): Article
    {
        static $n = 0;
        $n++;

        return Article::create(array_merge([
            'title' => "Artikel $n", 'slug' => "artikel-$n", 'category' => 'Mesin', 'body' => "Isi artikel $n yang cukup panjang.",
            'is_published' => true, 'published_at' => now()->subHour(),
        ], $o));
    }

    private function admin(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $u = User::factory()->create();
        $u->assignRole('admin');

        return $u;
    }

    // ------------------------------------------------------------ pemisahan profil vs toko

    public function test_company_profile_pages_have_no_login_or_cart_but_the_shop_does(): void
    {
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('login'), $home);
        $this->assertStringNotContainsString(route('customer.cart.index'), $home);
        $this->assertStringContainsString(route('products.index'), $home);       // jalan masuk ke toko

        $shop = $this->get(route('products.index'))->assertOk()->getContent();
        $this->assertStringContainsString(route('login'), $shop);
        $this->assertStringContainsString(route('customer.cart.index'), $shop);
        $this->assertStringContainsString('Kembali ke website', $shop);           // jalan pulang ke profil
    }

    public function test_home_has_no_3d_viewer_categories_or_products(): void
    {
        $cat = Category::unguarded(fn () => Category::create(['name' => 'Espresso', 'slug' => 'espresso', 'is_active' => true]));
        Product::unguarded(fn () => Product::create(['category_id' => $cat->id, 'name' => 'Mesin Z', 'slug' => 'mesin-z', 'sku' => 'Z1', 'price' => 1000, 'stock' => 2, 'is_active' => true, 'is_featured' => true]));

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('model-viewer', $html);
        $this->assertStringNotContainsString('Belanja per Kategori', $html);
        $this->assertStringNotContainsString('Produk Unggulan', $html);
        $this->assertStringNotContainsString('Mesin Z', $html);
    }

    // ------------------------------------------------------------ artikel (Insight)

    public function test_only_published_and_due_articles_are_public(): void
    {
        $live = $this->article(['title' => 'Terbit Hari Ini']);
        $draft = $this->article(['title' => 'Masih Draft', 'is_published' => false]);
        $future = $this->article(['title' => 'Terjadwal Besok', 'published_at' => now()->addDay()]);

        $this->get(route('articles.index'))->assertOk()->assertSee('Terbit Hari Ini')->assertDontSee('Masih Draft')->assertDontSee('Terjadwal Besok');
        $this->get(route('articles.show', $live->slug))->assertOk()->assertSee('Terbit Hari Ini');
        $this->get(route('articles.show', $draft->slug))->assertNotFound();
        $this->get(route('articles.show', $future->slug))->assertNotFound();
    }

    public function test_article_search_and_category_filter(): void
    {
        $this->article(['title' => 'Panduan Grinder', 'category' => 'Mesin']);
        $this->article(['title' => 'Strategi Menu Musim Panas', 'category' => 'Menu']);

        $this->get(route('articles.index', ['q' => 'grinder']))->assertOk()->assertSee('Panduan Grinder')->assertDontSee('Strategi Menu');
        $this->get(route('articles.index', ['kategori' => 'menu']))->assertOk()->assertSee('Strategi Menu Musim Panas')->assertDontSee('Panduan Grinder');
        $this->get(route('articles.index', ['q' => 'tidak-ada-sama-sekali']))->assertOk()->assertSee('Belum ada artikel yang cocok');
    }

    public function test_article_body_is_rendered_from_markdown_and_raw_html_is_stripped(): void
    {
        $a = $this->article(['body' => "## Judul Bagian\n\nTeks **tebal** <script>alert('x')</script> dan [tautan](javascript:alert(1)).\n\n- satu\n- dua"]);

        $html = $this->get(route('articles.show', $a->slug))->assertOk()->getContent();

        $this->assertStringContainsString('<h2>Judul Bagian</h2>', $html);
        $this->assertStringContainsString('<strong>tebal</strong>', $html);
        $this->assertStringNotContainsString("<script>alert('x')", $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_video_url_becomes_a_safe_embed_only_for_youtube_and_vimeo(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', (new Article(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']))->video_embed['src']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', (new Article(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']))->video_embed['src']);
        $this->assertSame('https://player.vimeo.com/video/123456789', (new Article(['video_url' => 'https://vimeo.com/123456789']))->video_embed['src']);
        $this->assertNull((new Article(['video_url' => 'https://evil.example.com/watch?v=dQw4w9WgXcQ']))->video_embed);
    }

    public function test_views_are_counted_once_per_session(): void
    {
        $a = $this->article();

        $this->get(route('articles.show', $a->slug));
        $this->get(route('articles.show', $a->slug));

        $this->assertSame(1, $a->fresh()->views);
    }

    public function test_home_shows_latest_three_articles_and_link_to_all(): void
    {
        foreach (range(1, 4) as $i) {
            $this->article(['title' => "Berita $i", 'published_at' => now()->subDays(5 - $i)]);
        }

        $this->get('/')->assertOk()->assertSee('Berita 4')->assertSee('Berita 3')->assertSee('Berita 2')->assertDontSee('Berita 1')->assertSee('Lihat Semua Artikel');
    }

    // ------------------------------------------------------------ admin artikel & milestone

    public function test_admin_can_write_schedule_and_unpublish_articles(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.articles.store'), ['title' => 'Artikel Baru Admin', 'category' => 'Bisnis', 'body' => 'Isi.', 'is_published' => 1, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])
            ->assertRedirect(route('admin.articles.index'));

        $a = Article::where('title', 'Artikel Baru Admin')->firstOrFail();
        $this->assertSame('artikel-baru-admin', $a->slug);
        $this->assertNotNull($a->published_at);
        $this->get(route('articles.show', $a->slug))->assertOk();

        $this->actingAs($admin)->patch(route('admin.articles.toggle-publish', $a->id));
        $this->get(route('articles.show', $a->slug))->assertNotFound();

        // validasi: video harus YouTube/Vimeo, slider wajib punya sampul
        $this->actingAs($admin)->post(route('admin.articles.store'), ['title' => 'X', 'category' => 'Bisnis', 'body' => 'Isi', 'video_url' => 'https://evil.example.com/x'])->assertSessionHasErrors('video_url');
        $this->actingAs($admin)->post(route('admin.articles.store'), ['title' => 'Y', 'category' => 'Bisnis', 'body' => 'Isi', 'is_featured' => 1])->assertSessionHasErrors('is_featured');
        $this->actingAs($admin)->get(route('admin.articles.index'))->assertOk()->assertSee('Artikel Baru Admin');
        $this->actingAs($admin)->get(route('admin.articles.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.articles.edit', $a->id))->assertOk();
    }

    public function test_staff_without_permission_cannot_manage_articles(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        $this->actingAs($staff)->get(route('admin.articles.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.milestones.index'))->assertForbidden();
    }

    public function test_milestones_appear_on_home_and_about_and_are_managed_by_admin(): void
    {
        foreach ([['2026', 'Katalog dibuka'], ['2026', 'Pembayaran otomatis'], ['Berikutnya', 'Insight'], ['2027', 'Cabang baru'], ['2028', 'Tidak tampil di beranda']] as $i => [$y, $t]) {
            Milestone::create(['year' => $y, 'title' => $t, 'sort_order' => $i, 'is_active' => true]);
        }
        Milestone::create(['year' => '2029', 'title' => 'Disembunyikan', 'sort_order' => 9, 'is_active' => false]);

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Katalog dibuka', $home);
        $this->assertStringNotContainsString('Tidak tampil di beranda', $home, 'beranda hanya memuat 4 pertama');

        $this->get(route('about'))->assertOk()->assertSee('Tidak tampil di beranda')->assertSee('id="perjalanan"', false)->assertDontSee('Disembunyikan');

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.milestones.store'), ['year' => '2030', 'title' => 'Milestone Baru', 'is_active' => 1])->assertRedirect(route('admin.milestones.index'));
        $this->actingAs($admin)->get(route('admin.milestones.index'))->assertOk()->assertSee('Milestone Baru');
    }

    public function test_milestone_sections_stay_hidden_when_there_is_no_content(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Perjalanan kami')->assertDontSee('Insight &amp; pengetahuan', false);
        $this->get(route('articles.index'))->assertOk()->assertSee('Artikel akan segera hadir');
    }

    // ------------------------------------------------------------ keranjang gaya marketplace

    public function test_cart_quantity_autosaves_clamps_to_stock_and_returns_indonesian_messages(): void
    {
        $user = User::factory()->create();
        $cat = Category::unguarded(fn () => Category::create(['name' => 'K', 'slug' => 'k', 'is_active' => true]));
        $p = Product::unguarded(fn () => Product::create(['category_id' => $cat->id, 'name' => 'Grinder F64', 'slug' => 'f64', 'sku' => 'F64', 'price' => 20000000, 'stock' => 3, 'is_active' => true]));

        $this->actingAs($user)->withSession(['cart' => [$p->id => ['name' => 'Grinder F64', 'price' => 20000000, 'quantity' => 1, 'stock' => 3, 'image' => null, 'slug' => 'f64']]]);

        // jumlah valid: tersimpan dan total dihitung ulang
        $this->patchJson(route('customer.cart.update', $p->id), ['quantity' => 2])
            ->assertOk()->assertJson(['quantity' => 2, 'max' => 3, 'message' => null, 'line_subtotal_label' => 'Rp 40.000.000', 'total_label' => 'Rp 40.000.000']);

        // melebihi stok: otomatis disesuaikan ke stok, dengan pesan bahasa Indonesia
        $r = $this->patchJson(route('customer.cart.update', $p->id), ['quantity' => 4])->assertOk();
        $this->assertSame(3, $r->json('quantity'));
        $this->assertSame('Stok tersedia hanya 3. Jumlah disesuaikan.', $r->json('message'));
        $this->assertSame(3, session('cart')[$p->id]['quantity']);

        // bukan angka: pesan Indonesia, bukan pesan bawaan browser
        $this->patchJson(route('customer.cart.update', $p->id), ['quantity' => 'abc'])->assertStatus(422)->assertJsonPath('errors.quantity.0', 'Jumlah harus berupa angka.');
    }

    public function test_cart_page_uses_stepper_without_update_button(): void
    {
        $user = User::factory()->create();
        $cat = Category::unguarded(fn () => Category::create(['name' => 'K2', 'slug' => 'k2', 'is_active' => true]));
        $p = Product::unguarded(fn () => Product::create(['category_id' => $cat->id, 'name' => 'Produk A', 'slug' => 'produk-a', 'sku' => 'A1', 'price' => 1000, 'stock' => 5, 'is_active' => true]));
        $this->actingAs($user)->withSession(['cart' => [$p->id => ['name' => 'Produk A', 'price' => 1000, 'quantity' => 1, 'stock' => 5, 'image' => null, 'slug' => 'produk-a']]]);

        $this->get(route('customer.cart.index'))->assertOk()->assertSee('Kurangi jumlah')->assertSee('cartLine', false)->assertDontSee('Perbarui');
    }
}
