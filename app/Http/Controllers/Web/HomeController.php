<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Testimonial;

/**
 * Halaman publik. Semua konten berasal dari database/pengaturan (PRD 6.5: tanpa hardcode).
 */
class HomeController extends Controller
{
    public function index()
    {
        $featuredProducts = Product::where('is_active', true)
            ->where('is_featured', true)
            ->with(['category', 'brand', 'images'])
            ->latest()
            ->take(8)
            ->get();

        // Kategori utama yang punya produk (termasuk dari sub-kategorinya). Yang kosong tidak ditampilkan:
        // kartu "0 produk" hanya mengarah ke halaman kosong. Yang terbanyak jadi kartu besar.
        $withCount = ['products' => fn ($q) => $q->where('is_active', true)];
        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount($withCount)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->withCount($withCount)])
            ->orderBy('name')
            ->get()
            ->each(fn ($c) => $c->products_count += $c->children->sum('products_count'))
            ->filter(fn ($c) => $c->products_count > 0)
            ->sortByDesc('products_count')
            ->values()
            ->take(5);

        $testimonials = $this->homeTestimonials();

        // scopeActive: aktif DAN dalam jadwal tayang (PRD 6.4 "Banner tampil sesuai jadwal")
        $banners = Banner::active()->orderBy('sort_order')->orderBy('id')->get();

        $partners = Partner::active()->orderBy('sort_order')->orderBy('name')->get();

        return view('customer.home', compact('featuredProducts', 'categories', 'testimonials', 'banners', 'partners'));
    }

    public function about()
    {
        $partners = Partner::active()->orderBy('sort_order')->orderBy('name')->get();

        return view('customer.about', compact('partners'));
    }

    public function faq()
    {
        $faqs = Faq::active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('category');

        return view('customer.faq', compact('faqs'));
    }

    public function branches()
    {
        $branches = Branch::active()->orderBy('name')->get();

        return view('customer.branches', compact('branches'));
    }

    /**
     * Testimoni untuk beranda (maks. 6). Bukan sekadar "6 terbaru", karena ulasan bagus tidak boleh tergeser
     * ulasan biasa dan ulasan ganda membuat situs terlihat tidak meyakinkan.
     *   1) Yang disematkan admin lebih dulu (Admin > Testimoni > Sematkan).
     *   2) Sisa slot diisi otomatis: rating tinggi, isi cukup informatif, satu per nama, tidak ada isi yang (hampir) sama.
     */
    private function homeTestimonials(int $limit = 6)
    {
        $norm = fn ($t) => mb_strtolower(preg_replace('/\W+/u', ' ', trim((string) $t->content)));
        $picked = collect();
        $names = [];
        $texts = [];

        $take = function ($t) use (&$picked, &$names, &$texts, $norm) {
            $picked->push($t);
            $names[] = mb_strtolower(trim((string) $t->customer_name));
            $texts[] = $norm($t);
        };

        $pool = Testimonial::approved()->latest()->take(100)->get();

        foreach ($pool->where('is_featured', true)->sortByDesc('updated_at')->take($limit) as $t) {
            $take($t);
        }

        $auto = $pool->where('is_featured', false)
            ->filter(fn ($t) => $t->rating >= 4 && mb_strlen(trim((string) $t->content)) >= 15)
            ->sortBy([['rating', 'desc'], fn ($a, $b) => mb_strlen($b->content) <=> mb_strlen($a->content), ['created_at', 'desc']]);

        foreach ($auto as $t) {
            if ($picked->count() >= $limit) {
                break;
            }
            if (in_array(mb_strtolower(trim((string) $t->customer_name)), $names, true)) {
                continue;       // satu per nama
            }
            $text = $norm($t);
            foreach ($texts as $seen) {
                similar_text($text, $seen, $pct);
                if ($pct >= 85) {
                    continue 2; // isi (hampir) sama dengan yang sudah terpilih, termasuk salah ketik
                }
            }
            $take($t);
        }

        return $picked->values();
    }
}
