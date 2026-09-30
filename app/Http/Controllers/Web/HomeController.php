<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Testimonial;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\Branch;

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
            ->take(6)
            ->get();

        $testimonials = Testimonial::where('is_approved', true)
            ->latest()
            ->take(6)
            ->get();

        $banners = Banner::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('customer.home', compact(
            'featuredProducts',
            'categories',
            'testimonials',
            'banners'
        ));
    }

    public function about()
    {
        return view('customer.about');
    }

    public function faq()
    {
        $faqs = Faq::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('customer.faq', compact('faqs'));
    }

    public function branches()
    {
        $branches = Branch::where('is_active', true)->get();

        return view('customer.branches', compact('branches'));
    }
}