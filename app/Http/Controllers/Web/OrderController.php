<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * List semua pesanan customer.
     */
    public function index()
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items', 'payment'])
            ->latest()
            ->paginate(10);

        return view('customer.orders.index', compact('orders'));
    }

    /**
     * Detail pesanan.
     */
    public function show($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', auth()->id())
            ->with(['items.product', 'payment', 'shipment', 'return'])
            ->firstOrFail();

        return view('customer.orders.show', compact('order'));
    }

    /**
     * Upload bukti pembayaran.
     */
    public function pay(Request $request, $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        if ($order->status !== 'menunggu_bayar') {
            return back()->with('error', 'Pesanan ini sudah tidak menunggu pembayaran.');
        }

        $validated = $request->validate([
            'method' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0',
            'proof_image' => 'required|image|max:2048',
            'note' => 'nullable|string',
        ]);

        $path = $request->file('proof_image')->store('payments', 'public');

        Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'method' => $validated['method'],
                'amount' => $validated['amount'],
                'proof_image' => $path,
                'status' => 'pending',
                'note' => $validated['note'] ?? null,
            ]
        );

        return back()->with('success', 'Bukti pembayaran berhasil diunggah. Menunggu verifikasi admin.');
    }
}