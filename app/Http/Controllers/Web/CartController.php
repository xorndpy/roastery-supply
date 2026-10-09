<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $carts)
    {
    }

    /**
     * Tampilkan halaman cart. Harga & stok disegarkan dari database.
     */
    public function index()
    {
        ['cart' => $cart, 'total' => $total, 'notices' => $notices] = $this->carts->refresh();

        if ($notices) {
            session()->flash('warning', implode(' ', $notices));
        }

        return view('customer.cart.index', compact('cart', 'total'));
    }

    /**
     * Tambah produk ke cart (guest boleh; login diminta saat checkout).
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        $product = Product::with('images')
            ->where('is_active', true)
            ->find($request->product_id);

        if (!$product) {
            return back()->with('error', 'Produk tidak tersedia.');
        }

        $cart = session()->get('cart', []);
        $newQty = ($cart[$product->id]['quantity'] ?? 0) + (int) $request->quantity;

        if ($newQty > $product->stock) {
            return back()->with('error', "Stok tidak mencukupi (tersisa {$product->stock}).");
        }

        $cart[$product->id] = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'weight' => (int) $product->weight,
            'price' => $product->price,
            'quantity' => $newQty,
            'image' => $product->images->first()->image ?? null,
            'stock' => $product->stock,
        ];

        session()->put('cart', $cart);

        return redirect()->route('customer.cart.index')
            ->with('success', 'Produk ditambahkan ke keranjang.');
    }

    /**
     * Update quantity produk di cart.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        $cart = session()->get('cart', []);

        if (!isset($cart[$id])) {
            return back()->with('error', 'Produk tidak ada di keranjang.');
        }

        $product = Product::where('is_active', true)->find($id);

        if (!$product) {
            unset($cart[$id]);
            session()->put('cart', $cart);

            return back()->with('error', 'Produk sudah tidak tersedia dan dihapus dari keranjang.');
        }

        if ($request->quantity > $product->stock) {
            return back()->with('error', "Stok tidak mencukupi (tersisa {$product->stock}).");
        }

        $cart[$id]['quantity'] = (int) $request->quantity;
        session()->put('cart', $cart);

        return back()->with('success', 'Keranjang diperbarui.');
    }

    /**
     * Hapus produk dari cart.
     */
    public function destroy($id)
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            unset($cart[$id]);
            session()->put('cart', $cart);
        }

        return back()->with('success', 'Produk dihapus dari keranjang.');
    }
}
