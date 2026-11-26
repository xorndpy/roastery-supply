<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

/** Perbandingan produk: pilihan disimpan di sesi (tanpa login), maksimal 4 produk. */
class CompareController extends Controller
{
    public const LIMIT = 4;

    public function index()
    {
        $ids = array_values(array_unique(array_map('intval', (array) session('compare', []))));
        $found = Product::with(['brand', 'category', 'images'])
            ->where('is_active', true)->whereIn('id', $ids)->get()->keyBy('id');

        // Produk yang sudah dihapus/nonaktif dibuang dari pilihan.
        $products = collect($ids)->map(fn ($id) => $found->get($id))->filter()->values();
        session(['compare' => $products->pluck('id')->all()]);

        // Gabungkan baris spesifikasi dari semua produk (kunci dicocokkan tanpa membedakan huruf besar/kecil).
        $specLabels = [];
        $specValues = [];
        foreach ($products as $p) {
            foreach ((array) $p->specs as $row) {
                $label = trim((string) ($row['key'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($label === '' || $value === '') {
                    continue;
                }
                $k = mb_strtolower($label);
                $specLabels[$k] ??= $label;
                $specValues[$k][$p->id] = $value;
            }
        }

        return view('customer.compare.index', [
            'products' => $products,
            'specLabels' => $specLabels,
            'specValues' => $specValues,
            'limit' => self::LIMIT,
        ]);
    }

    public function toggle(Request $request, string $slug)
    {
        $product = Product::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $ids = array_values(array_unique(array_map('intval', (array) session('compare', []))));
        $selected = in_array($product->id, $ids, true);
        $message = null;

        if ($selected) {
            $ids = array_values(array_diff($ids, [$product->id]));
            $selected = false;
        } elseif (count($ids) >= self::LIMIT) {
            $message = 'Maksimal ' . self::LIMIT . ' produk. Hapus salah satu dulu.';
        } else {
            $ids[] = $product->id;
            $selected = true;
        }
        session(['compare' => $ids]);

        if ($request->expectsJson()) {
            return response()->json(['selected' => $selected, 'count' => count($ids), 'message' => $message], $message ? 422 : 200);
        }

        return back()->with($message ? 'error' : 'success', $message ?? ($selected ? 'Ditambahkan ke perbandingan.' : 'Dihapus dari perbandingan.'));
    }

    public function clear(Request $request)
    {
        session()->forget('compare');

        return $request->expectsJson() ? response()->json(['count' => 0]) : redirect()->route('products.index');
    }
}
