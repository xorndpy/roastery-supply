<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::withCount('products');

        // Filter status
        if ($request->status === 'active') {
            $query->where('is_active', true);
        } elseif ($request->status === 'inactive') {
            $query->where('is_active', false);
        }

        // Search
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $brands = $query->orderBy('name')->paginate(20)->withQueryString();

        $stats = [
            'total' => Brand::count(),
            'active' => Brand::where('is_active', true)->count(),
            'inactive' => Brand::where('is_active', false)->count(),
        ];

        return view('admin.brands.index', compact('brands', 'stats'));
    }

    public function create()
    {
        return view('admin.brands.form', [
            'brand' => new Brand(),
            'mode' => 'create',
        ]);
    }

    public function store(StoreBrandRequest $request)
    {
        $data = $request->validated();

        // Auto-generate slug
        $data['slug'] = $this->generateSlug($data['name']);

        // Handle upload logo
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand = Brand::create($data);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', "Merek {$brand->name} berhasil ditambahkan.");
    }

    public function show(Brand $brand)
    {
        $brand->load('products');

        return view('admin.brands.show', compact('brand'));
    }

    public function edit(Brand $brand)
    {
        return view('admin.brands.form', [
            'brand' => $brand,
            'mode' => 'edit',
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $data = $request->validated();

        // Update slug kalau nama berubah
        if ($data['name'] !== $brand->name) {
            $data['slug'] = $this->generateSlug($data['name'], $brand->id);
        }

        // Handle upload logo baru
        if ($request->hasFile('logo')) {
            // Hapus logo lama
            if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
                Storage::disk('public')->delete($brand->logo);
            }
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand->update($data);

        return redirect()
            ->route('admin.brands.index')
            ->with('success', "Merek {$brand->name} berhasil diupdate.");
    }

    public function destroy(Brand $brand)
    {
        // Cek apakah punya produk
        if ($brand->products()->count() > 0) {
            return back()->with('error', 'Merek tidak bisa dihapus karena masih memiliki produk.');
        }

        $name = $brand->name;

        // Hapus logo
        if ($brand->logo && Storage::disk('public')->exists($brand->logo)) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->delete();

        return redirect()
            ->route('admin.brands.index')
            ->with('success', "Merek {$name} berhasil dihapus.");
    }

    public function toggleActive(Brand $brand)
    {
        $brand->update(['is_active' => !$brand->is_active]);

        return back()->with('success', 'Status merek berhasil diubah.');
    }

    /**
     * Generate slug unik untuk merek.
     */
    protected function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Brand::where('slug', $slug);
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