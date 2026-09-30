<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * List produk dengan filter.
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'images']);

        // Filter kategori
        if ($request->category) {
            $query->where('category_id', $request->category);
        }

        // Filter merek
        if ($request->brand) {
            $query->where('brand_id', $request->brand);
        }

        // Filter status
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($request->status === 'low_stock') {
            $query->whereColumn('stock', '<=', 'stock_threshold');
        }

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        // Sort
        $sort = $request->sort ?? 'latest';
        match ($sort) {
            'name_asc' => $query->orderBy('name', 'asc'),
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'stock_asc' => $query->orderBy('stock', 'asc'),
            'stock_desc' => $query->orderBy('stock', 'desc'),
            default => $query->latest(),
        };

        $products = $query->paginate(15)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        // Statistik
        $stats = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'low_stock' => Product::whereColumn('stock', '<=', 'stock_threshold')->count(),
            'out_of_stock' => Product::where('stock', 0)->count(),
        ];

        return view('admin.products.index', compact('products', 'categories', 'brands', 'stats'));
    }

    /**
     * Form create produk.
     */
    public function create()
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.form', [
            'product' => new Product(),
            'categories' => $categories,
            'brands' => $brands,
            'mode' => 'create',
        ]);
    }

    /**
     * Simpan produk baru.
     */
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();

        // Handle nullable fields + checkbox booleans
        $data['weight'] = $data['weight'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_featured'] = $request->boolean('is_featured', false);

        // Generate SKU kalau kosong
        if (empty($data['sku'])) {
            $data['sku'] = $this->productService->generateSku($data['category_id']);
        }

        // Generate slug
        $data['slug'] = $this->productService->generateSlug($data['name']);

        // Handle upload 3D
        if ($request->hasFile('model_3d')) {
            $data['model_3d'] = $this->productService->uploadModel3d($request->file('model_3d'));
        }

        // Buat produk
        $product = Product::create($data);

        // Upload gambar
        if ($request->hasFile('images')) {
            $this->productService->syncImages($product, $request->file('images'));
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Produk {$product->name} berhasil ditambahkan.");
    }

    /**
     * Detail produk.
     */
    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'images', 'stockMovements.user']);

        return view('admin.products.show', compact('product'));
    }

    /**
     * Form edit produk.
     */
    public function edit(Product $product)
    {
        $product->load('images');
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.form', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
            'mode' => 'edit',
        ]);
    }

    /**
     * Update produk.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        // Handle nullable fields + checkbox booleans
        $data['weight'] = $data['weight'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_featured'] = $request->boolean('is_featured', false);

        // Generate SKU kalau kosong
        if (empty($data['sku'])) {
            $data['sku'] = $product->sku;
        }

        // Update slug kalau nama berubah
        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->productService->generateSlug($data['name'], $product->id);
        }

        // Handle upload 3D baru
        if ($request->hasFile('model_3d')) {
            $this->productService->deleteFile($product->model_3d);
            $data['model_3d'] = $this->productService->uploadModel3d($request->file('model_3d'));
        }

        $product->update($data);

        // Sync gambar (keep + upload baru)
        $keepImages = $request->input('keep_images', []);
        if ($request->hasFile('images') || !empty($keepImages)) {
            $this->productService->syncImages(
                $product,
                $request->file('images', []),
                $keepImages
            );
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Produk {$product->name} berhasil diupdate.");
    }

    /**
     * Soft delete produk.
     */
    public function destroy(Product $product)
    {
        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Produk {$name} berhasil dihapus.");
    }

    /**
     * Toggle is_active.
     */
    public function toggleActive(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);

        return back()->with('success', 'Status produk berhasil diubah.');
    }

    /**
     * Toggle is_featured.
     */
    public function toggleFeatured(Product $product)
    {
        $product->update(['is_featured' => !$product->is_featured]);

        return back()->with('success', 'Status unggulan berhasil diubah.');
    }
}