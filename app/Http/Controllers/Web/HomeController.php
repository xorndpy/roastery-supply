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

        $categories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->take(6)
            ->get();

        $testimonials = Testimonial::approved()->latest()->take(6)->get();

        // scopeActive: aktif DAN dalam jadwal tayang (PRD 6.4 "Banner tampil sesuai jadwal")
        $banners = Banner::active()->orderBy('sort_order')->orderBy('id')->get();

        $partners = Partner::active()->orderBy('sort_order')->orderBy('name')->get();

        return view('customer.home', compact('featuredProducts', 'categories', 'testimonials', 'banners', 'partners'));
    }

    public function about()
    {
        return view('customer.about');
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
}
