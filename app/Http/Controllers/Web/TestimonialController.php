<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Testimonial;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Customer menulis testimoni dari pesanan yang sudah selesai.
 * Maksimal satu per order, dan baru tampil di website setelah disetujui admin (PRD 6.4).
 */
class TestimonialController extends Controller
{
    public function store(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->where('user_id', auth()->id())->firstOrFail();

        if ($order->status !== 'selesai') {
            return back()->with('error', 'Testimoni bisa ditulis setelah pesanan selesai.');
        }

        $data = $request->validate([
            'rating'  => 'required|integer|between:1,5',
            'content' => 'required|string|min:10|max:1000',
        ]);

        try {
            Testimonial::create([
                'user_id'       => auth()->id(),
                'order_id'      => $order->id,
                'customer_name' => auth()->user()->name,
                'content'       => $data['content'],
                'rating'        => $data['rating'],
                'is_approved'   => false,
            ]);
        } catch (QueryException) {
            // kunci unik order_id: klik ganda / sudah pernah menulis
            return back()->with('error', 'Kamu sudah menulis testimoni untuk pesanan ini.');
        }

        return back()->with('success', 'Terima kasih! Testimoni kamu akan tampil setelah ditinjau admin.');
    }
}
