<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Http\Request;

class KatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('is_active', true)
            ->with(['category', 'brand', 'images']);

        // Filter kategori
        if ($request->category) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter merek
        if ($request->brand) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('slug', $request->brand);
            });
        }

        // Search
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sort = $request->sort ?? 'latest';
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->whereNull('parent_id')->get();
        $brands = Brand::where('is_active', true)->get();

        return view('customer.katalog.index', compact('products', 'categories', 'brands'));
    }

    public function show($slug)
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->with(['category', 'brand', 'images'])
            ->firstOrFail();

        // Produk terkait (kategori sama)
        $relatedProducts = Product::where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('customer.katalog.show', compact('product', 'relatedProducts'));
    }
}