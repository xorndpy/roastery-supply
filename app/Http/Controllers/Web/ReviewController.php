<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Ulasan produk: hanya pembeli yang pesanannya berstatus "selesai" dan memuat produk ini.
 * Satu ulasan per user per produk (unique key di database).
 */
class ReviewController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $product = Product::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'content' => 'required|string|min:10|max:1000',
        ]);

        $bought = Order::where('user_id', auth()->id())->where('status', 'selesai')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))->exists();

        if (!$bought) {
            return back()->with('error', 'Ulasan hanya bisa ditulis setelah pesanan produk ini selesai.');
        }

        try {
            ProductReview::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'rating' => $data['rating'],
                'content' => $data['content'],
                'is_approved' => true,
            ]);
        } catch (QueryException) {
            return back()->with('error', 'Kamu sudah menulis ulasan untuk produk ini.');
        }

        return redirect()->to(route('products.show', $product->slug) . '#ulasan')->with('success', 'Terima kasih, ulasan kamu sudah tampil.');
    }
}
