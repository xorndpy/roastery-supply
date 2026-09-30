<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->with('error', 'Keranjang kosong.');
        }

        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        $customer = auth()->user()->customer;

        return view('customer.checkout.index', compact('cart', 'total', 'customer'));
    }

    public function store(Request $request)
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('customer.cart.index')
                ->with('error', 'Keranjang kosong.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'note' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // Update atau buat customer
            $customer = Customer::updateOrCreate(
                ['user_id' => auth()->id()],
                array_merge($validated, [
                    'name' => $validated['name'],
                    'email' => auth()->user()->email,
                ])
            );

            // Hitung total
            $subtotal = 0;
            foreach ($cart as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }

            // Buat order
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'status' => 'menunggu_bayar',
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_cost' => 0,
                'total' => $subtotal,
                'note' => $validated['note'] ?? null,
            ]);

            // Buat order items
            foreach ($cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name' => $item['name'],
                    'product_sku' => $item['slug'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);
            }

            DB::commit();

            // Clear cart
            session()->forget('cart');

            return redirect()->route('customer.orders.show', $order->order_number)
                ->with('success', 'Pesanan berhasil dibuat. Silakan lakukan pembayaran.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    private function generateOrderNumber()
    {
        $date = now()->format('Ymd');
        $count = Order::whereDate('created_at', today())->count() + 1;
        return 'ORD-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}