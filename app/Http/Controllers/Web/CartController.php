<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Tampilkan halaman cart.
     */
    public function index()
    {
        $cart = session()->get('cart', []);
        $total = 0;

        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return view('customer.cart.index', compact('cart', 'total'));
    }

    /**
     * Tambah produk ke cart.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($request->product_id);

        // Cek stok
        if ($product->stock < $request->quantity) {
            return back()->with('error', 'Stok tidak mencukupi.');
        }

        $cart = session()->get('cart', []);
        $id = $product->id;

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] += $request->quantity;

            if ($cart[$id]['quantity'] > $product->stock) {
                return back()->with('error', 'Stok tidak mencukupi.');
            }
        } else {
            $cart[$id] = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'quantity' => $request->quantity,
                'image' => $product->images->first()->image ?? null,
                'stock' => $product->stock,
            ];
        }

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
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = session()->get('cart', []);

        if (!isset($cart[$id])) {
            return back()->with('error', 'Produk tidak ada di keranjang.');
        }

        $product = Product::find($id);

        if ($product && $request->quantity > $product->stock) {
            return back()->with('error', 'Stok tidak mencukupi.');
        }

        $cart[$id]['quantity'] = $request->quantity;
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