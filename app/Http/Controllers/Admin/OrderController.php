<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * List pesanan dengan filter.
     */
    public function index(Request $request)
    {
        $query = Order::with(['customer', 'items', 'payment']);

        // Filter status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Search by order number atau nama customer
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($cq) use ($request) {
                      $cq->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        // Filter tanggal
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        // Statistik
        $stats = [
            'all' => Order::count(),
            'menunggu_bayar' => Order::where('status', 'menunggu_bayar')->count(),
            'dikonfirmasi' => Order::where('status', 'dikonfirmasi')->count(),
            'diproses' => Order::where('status', 'diproses')->count(),
            'dikirim' => Order::where('status', 'dikirim')->count(),
            'selesai' => Order::where('status', 'selesai')->count(),
            'batal' => Order::where('status', 'batal')->count(),
        ];

        return view('admin.orders.index', compact('orders', 'stats'));
    }

    /**
     * Detail pesanan.
     */
    public function show(Order $order)
    {
        $order->load(['customer', 'items.product', 'payment', 'shipment', 'return']);

        return view('admin.orders.show', compact('order'));
    }

    /**
     * Konfirmasi pembayaran → status jadi "dikonfirmasi".
     * Di sini stok berkurang (sesuai PRD).
     */
    public function confirmPayment(Order $order)
    {
        if ($order->status !== 'menunggu_bayar') {
            return back()->with('error', 'Pesanan ini tidak dalam status menunggu bayar.');
        }

        if (!$order->payment || $order->payment->status !== 'pending') {
            return back()->with('error', 'Belum ada bukti pembayaran yang diunggah.');
        }

        DB::beginTransaction();

        try {
            // Update payment
            $order->payment->update([
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            // Update order status
            $order->update(['status' => 'dikonfirmasi']);

            // Kurangi stok produk + catat stock movement
            foreach ($order->items as $item) {
                $product = $item->product;

                if (!$product) {
                    continue;
                }

                if ($product->stock < $item->quantity) {
                    DB::rollBack();
                    return back()->with('error', "Stok produk {$product->name} tidak mencukupi.");
                }

                $product->decrement('stock', $item->quantity);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $item->quantity,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'note' => 'Pesanan ' . $order->order_number,
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            return back()->with('success', 'Pembayaran dikonfirmasi. Stok telah dikurangi.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal konfirmasi: ' . $e->getMessage());
        }
    }

    /**
     * Update status pesanan (diproses).
     */
    public function process(Order $order)
    {
        if ($order->status !== 'dikonfirmasi') {
            return back()->with('error', 'Pesanan harus dalam status dikonfirmasi.');
        }

        $order->update(['status' => 'diproses']);

        return back()->with('success', 'Pesanan sedang diproses.');
    }

    /**
     * Input resi & kirim pesanan.
     */
    public function ship(Request $request, Order $order)
    {
        if (!in_array($order->status, ['dikonfirmasi', 'diproses'])) {
            return back()->with('error', 'Pesanan belum siap dikirim.');
        }

        $validated = $request->validate([
            'courier' => 'required|string|max:100',
            'service' => 'nullable|string|max:100',
            'tracking_number' => 'required|string|max:100',
            'cost' => 'nullable|numeric|min:0',
        ]);

        Shipment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'courier' => $validated['courier'],
                'service' => $validated['service'] ?? null,
                'tracking_number' => $validated['tracking_number'],
                'cost' => $validated['cost'] ?? 0,
                'status' => 'shipped',
                'shipped_at' => now(),
            ]
        );

        $order->update(['status' => 'dikirim']);

        return back()->with('success', 'Resi berhasil diinput. Pesanan sedang dikirim.');
    }

    /**
     * Selesaikan pesanan.
     */
    public function complete(Order $order)
    {
        if ($order->status !== 'dikirim') {
            return back()->with('error', 'Pesanan harus dalam status dikirim.');
        }

        $order->update(['status' => 'selesai']);

        if ($order->shipment) {
            $order->shipment->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
        }

        return back()->with('success', 'Pesanan selesai.');
    }

    /**
     * Batalkan pesanan + kembalikan stok (kalau sudah dikurangi).
     */
    public function cancel(Order $order)
    {
        if (in_array($order->status, ['dikirim', 'selesai'])) {
            return back()->with('error', 'Pesanan yang sudah dikirim/selesai tidak bisa dibatalkan.');
        }

        DB::beginTransaction();

        try {
            // Kembalikan stok kalau status sudah "dikonfirmasi" atau lebih
            if (in_array($order->status, ['dikonfirmasi', 'diproses'])) {
                foreach ($order->items as $item) {
                    $product = $item->product;
                    if (!$product) continue;

                    $product->increment('stock', $item->quantity);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'reference_type' => 'order_cancel',
                        'reference_id' => $order->id,
                        'note' => 'Pembatalan ' . $order->order_number,
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            $order->update(['status' => 'batal']);

            DB::commit();

            return back()->with('success', 'Pesanan dibatalkan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal batalkan: ' . $e->getMessage());
        }
    }

    /**
     * Generate invoice PDF.
     */
    public function invoice(Order $order)
    {
        $order->load(['customer', 'items']);
        return view('admin.orders.invoice', compact('order'));
    }
}