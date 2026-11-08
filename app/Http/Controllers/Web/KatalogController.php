<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
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
            // Kategori induk juga menampilkan produk dari sub-kategorinya.
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category)
                  ->orWhereHas('parent', fn ($p) => $p->where('slug', $request->category));
            });
        }

        // Filter merek
        if ($request->brand) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('slug', $request->brand);
            });
        }

        // Search (nama, SKU, deskripsi)
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('sku', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%"));
        }

        // Harga & stok
        if ($request->filled('min_price')) $query->where('price', '>=', (int) $request->min_price);
        if ($request->filled('max_price')) $query->where('price', '<=', (int) $request->max_price);
        if ($request->boolean('in_stock')) $query->where('stock', '>', 0);

        // Sorting
        $sort = $request->sort ?? 'latest';
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        // Filter kategori: hanya yang berisi produk (termasuk sub-kategori); kategori yang sedang dipilih tetap tampil.
        $wc = ['products' => fn ($q) => $q->where('is_active', true)];
        $categories = Category::where('is_active', true)->whereNull('parent_id')
            ->withCount($wc)->with(['children' => fn ($q) => $q->where('is_active', true)->withCount($wc)])
            ->orderBy('name')->get()
            ->filter(fn ($c) => $c->products_count + $c->children->sum('products_count') > 0 || $c->slug === $request->category)
            ->values();
        $brands = Brand::where('is_active', true)->get();

        // Halaman kategori: banner + chip sub-kategori (anak dari kategori ini, atau saudara kalau ini sudah sub-kategori).
        $currentCategory = null;
        $subCategories = collect();
        if ($request->filled('category')) {
            $currentCategory = Category::where('is_active', true)->where('slug', $request->category)
                ->with(['parent', 'children' => fn ($q) => $q->where('is_active', true)->withCount($wc)->orderBy('name')])
                ->first();
            if ($currentCategory) {
                $subCategories = $currentCategory->children->isNotEmpty()
                    ? $currentCategory->children
                    : ($currentCategory->parent_id
                        ? Category::where('is_active', true)->where('parent_id', $currentCategory->parent_id)->withCount($wc)->orderBy('name')->get()
                        : collect());
                $subCategories = $subCategories->filter(fn ($sc) => $sc->products_count > 0)->values();
            }
        }

        // Beranda toko (tanpa filter, halaman 1): hero kategori + terbaru + sorotan.
        $landing = !array_filter($request->except('page', 'sort')) && (int) $request->query('page', 1) <= 1;
        $categoryCards = collect();
        $featured = collect();
        $latest = collect();
        if ($landing) {
            $withCount = ['products' => fn ($q) => $q->where('is_active', true)];
            $categoryCards = Category::where('is_active', true)->whereNull('parent_id')
                ->withCount($withCount)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->withCount($withCount)])
                ->orderBy('name')->get()
                ->each(fn ($c) => $c->products_count += $c->children->sum('products_count'))
                ->filter(fn ($c) => $c->products_count > 0)
                ->sortByDesc('products_count')->values()->take(12);
            // Kategori tanpa foto sendiri memakai foto produk pertamanya (termasuk sub-kategori).
            $categoryCards->each(function ($cat) {
                if (! empty($cat->image)) {
                    $cat->card_image = $cat->image;
                    return;
                }
                $ids = $cat->children->pluck('id')->push($cat->id);
                $cat->card_image = ProductImage::whereIn('product_id', Product::where('is_active', true)->whereIn('category_id', $ids)->select('id'))
                    ->orderByDesc('is_primary')->orderBy('sort_order')->value('image');
            });
            $featured = Product::where('is_active', true)->where('is_featured', true)
                ->with(['category', 'brand', 'images'])->latest()->take(6)->get();
            $latest = Product::where('is_active', true)
                ->with(['category', 'brand', 'images'])->latest()->take(10)->get();
        }

        return view('customer.katalog.index', compact('products', 'categories', 'brands', 'landing', 'categoryCards', 'featured', 'latest', 'currentCategory', 'subCategories'));
    }

    public function brands()
    {
        $brands = Brand::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')->get();

        return view('customer.katalog.brands', compact('brands'));
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
