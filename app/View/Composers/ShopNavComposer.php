<?php

namespace App\View\Composers;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * Data untuk navbar toko: menu kategori dan jumlah isi keranjang.
 * Dipisah dari Blade supaya view hanya menampilkan data, tidak menjalankan query.
 */
class ShopNavComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'navCats' => $this->categories(),
            'cartCount' => count((array) session('cart', [])),
        ]);
    }

    /** Menu kategori jarang berubah: simpan 5 menit supaya tidak query berat di setiap halaman. */
    private function categories()
    {
        return Cache::remember('shop.nav.categories', 300, function () {
            $activeProducts = ['products' => fn ($q) => $q->where('is_active', true)];

            return Category::where('is_active', true)->whereNull('parent_id')
                ->withCount($activeProducts)
                ->with(['children' => fn ($q) => $q->where('is_active', true)->withCount($activeProducts)])
                ->orderBy('name')->get()
                ->filter(fn ($c) => $c->products_count + $c->children->sum('products_count') > 0)
                ->take(12)->values();
        });
    }
}
