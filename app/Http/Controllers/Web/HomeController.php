<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Milestone;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Testimonial;

/**
 * Halaman publik. Semua konten berasal dari database/pengaturan (PRD 6.5: tanpa hardcode).
 */
class HomeController extends Controller
{
    /** Beranda = COMPANY PROFILE. Belanja (kategori, produk) ada di toko: /katalog. */
    public function index()
    {
        $testimonials = $this->homeTestimonials();

        // scopeActive: aktif DAN dalam jadwal tayang (PRD 6.4 "Banner tampil sesuai jadwal")
        $banners = Banner::active()->orderBy('sort_order')->orderBy('id')->take(2)->get();

        $partners = Partner::active()->orderBy('sort_order')->orderBy('name')->get();

        $articles = Article::published()->latest('published_at')->take(3)->get();
        $milestones = Milestone::active()->get();

        return view('customer.home', compact('testimonials', 'banners', 'partners', 'articles', 'milestones'));
    }

    public function about()
    {
        $partners = Partner::active()->orderBy('sort_order')->orderBy('name')->get();

        $milestones = Milestone::active()->get();

        return view('customer.about', compact('partners', 'milestones'));
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
