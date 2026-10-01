<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['parent', 'children'])
            ->withCount('products');

        // Filter status
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        // Filter parent (root only / all)
        if ($request->type === 'root') {
            $query->whereNull('parent_id');
        } elseif ($request->type === 'child') {
            $query->whereNotNull('parent_id');
        }

        // Search
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $categories = $query->orderBy('name')->paginate(20)->withQueryString();

        $stats = [
            'total' => Category::count(),
            'active' => Category::where('is_active', true)->count(),
            'root' => Category::whereNull('parent_id')->count(),
            'child' => Category::whereNotNull('parent_id')->count(),
        ];

        return view('admin.categories.index', compact('categories', 'stats'));
    }

    public function create()
    {
        $parents = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.categories.form', [
            'category' => new Category(),
            'parents' => $parents,
            'mode' => 'create',
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();

        // Auto-generate slug
        $data['slug'] = $this->generateSlug($data['name']);

        // Handle upload image
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Kategori {$category->name} berhasil ditambahkan.");
    }

    public function show(Category $category)
    {
        $category->load(['parent', 'children', 'products']);

        return view('admin.categories.show', compact('category'));
    }

    public function edit(Category $category)
    {
        // Cegah pilih diri sendiri sebagai parent
        $parents = Category::whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.categories.form', [
            'category' => $category,
            'parents' => $parents,
            'mode' => 'edit',
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();

        // Update slug kalau nama berubah
        if ($data['name'] !== $category->name) {
            $data['slug'] = $this->generateSlug($data['name'], $category->id);
        }

        // Handle upload image baru
        if ($request->hasFile('image')) {
            // Hapus image lama
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Kategori {$category->name} berhasil diupdate.");
    }

    public function destroy(Category $category)
    {
        // Cek apakah punya produk
        if ($category->products()->count() > 0) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih memiliki produk.');
        }

        // Cek apakah punya sub-kategori
        if ($category->children()->count() > 0) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih memiliki sub-kategori.');
        }

        $name = $category->name;

        // Hapus image
        if ($category->image && Storage::disk('public')->exists($category->image)) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', "Kategori {$name} berhasil dihapus.");
    }

    public function toggleActive(Category $category)
    {
        $category->update(['is_active' => !$category->is_active]);

        return back()->with('success', 'Status kategori berhasil diubah.');
    }

    /**
     * Generate slug unik untuk kategori.
     */
    protected function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Category::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}