<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * Daftar stok + peringatan stok menipis (PRD 6.1).
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand']);

        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        match ($request->status) {
            'out' => $query->where('stock', '<=', 0),
            'low' => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'stock_threshold'),
            'ok'  => $query->whereColumn('stock', '>', 'stock_threshold'),
            default => null,
        };

        // Yang paling mendesak di atas: habis, lalu menipis, lalu sisa terkecil.
        $products = $query->orderByRaw('CASE WHEN stock <= 0 THEN 0 WHEN stock <= stock_threshold THEN 1 ELSE 2 END')
            ->orderBy('stock')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Product::count(),
            'out'   => Product::where('stock', '<=', 0)->count(),
            'low'   => Product::where('stock', '>', 0)->whereColumn('stock', '<=', 'stock_threshold')->count(),
        ];

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('admin.stock.index', compact('products', 'stats', 'categories'));
    }

    /**
     * Penyesuaian stok manual. Setiap perubahan wajib meninggalkan baris riwayat.
     *  - in  : barang masuk (+)
     *  - out : barang keluar manual, mis. rusak/sampel (-)
     *  - set : stock opname, stok diset ke jumlah hitungan fisik
     */
    public function adjust(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'type'       => 'required|in:in,out,set',
            'quantity'   => 'required|integer|min:0|max:1000000',
            'note'       => 'nullable|string|max:255',
        ]);

        if ($validated['type'] !== 'in' && blank($validated['note'] ?? null)) {
            return back()->withInput()->with('error', 'Alasan wajib diisi untuk stok keluar dan stock opname.');
        }
        if ($validated['type'] !== 'set' && (int) $validated['quantity'] < 1) {
            return back()->withInput()->with('error', 'Jumlah minimal 1.');
        }

        try {
            $product = DB::transaction(function () use ($validated) {
                $product = Product::whereKey($validated['product_id'])->lockForUpdate()->firstOrFail();
                $qty = (int) $validated['quantity'];
                $before = $product->stock;

                [$delta, $type] = match ($validated['type']) {
                    'in'  => [$qty, 'in'],
                    'out' => [-$qty, 'out'],
                    'set' => [$qty - $before, 'adjustment'],
                };

                if ($before + $delta < 0) {
                    throw new DomainException("Stok \"{$product->name}\" hanya {$before}, tidak bisa dikurangi {$qty}.");
                }
                if ($delta === 0) {
                    throw new DomainException('Jumlah sama dengan stok sekarang, tidak ada perubahan.');
                }

                $product->update(['stock' => $before + $delta]);

                $note = trim(($validated['note'] ?? '') . ($validated['type'] === 'set' ? " (opname: {$before} → " . ($before + $delta) . ')' : ''));

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => $type,
                    // in/out disimpan positif (arah ada di type); adjustment bertanda (+/-).
                    'quantity'       => $type === 'adjustment' ? $delta : abs($delta),
                    'reference_type' => 'manual',
                    'note'           => $note ?: null,
                    'user_id'        => auth()->id(),
                ]);

                return $product;
            });
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Stok {$product->name} diperbarui menjadi {$product->stock}.");
    }

    /**
     * Riwayat mutasi stok (siapa, kapan, kenapa).
     */
    public function history(Request $request)
    {
        $query = StockMovement::with(['product', 'user'])->latest('id');

        if ($search = $request->search) {
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $movements = $query->paginate(30)->withQueryString();

        // Nomor order untuk kolom referensi (satu query, bukan N+1).
        $orderRefs = \App\Models\Order::whereIn(
            'id',
            $movements->whereIn('reference_type', ['order', 'order_cancel'])->pluck('reference_id')
        )->pluck('order_number', 'id');

        return view('admin.stock.history', compact('movements', 'orderRefs'));
    }
}
