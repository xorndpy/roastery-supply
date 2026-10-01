<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * List pembayaran dengan filter.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['order.customer', 'order.items', 'verifier']);

        // Filter status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Search by order number / customer name
        if ($request->search) {
            $query->whereHas('order', function ($q) use ($request) {
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

        $payments = $query->latest()->paginate(15)->withQueryString();

        // Statistik
        $stats = [
            'pending' => Payment::where('status', 'pending')->count(),
            'verified' => Payment::where('status', 'verified')->count(),
            'rejected' => Payment::where('status', 'rejected')->count(),
            'total_pending_amount' => Payment::where('status', 'pending')->sum('amount'),
        ];

        return view('admin.payments.index', compact('payments', 'stats'));
    }

    /**
     * Detail pembayaran.
     */
    public function show(Payment $payment)
    {
        $payment->load(['order.customer', 'order.items.product', 'verifier']);

        return view('admin.payments.show', compact('payment'));
    }

    /**
     * Verify pembayaran → status jadi "dikonfirmasi" + stok berkurang.
     */
    public function verify(Payment $payment)
    {
        // Cek status payment
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Pembayaran ini sudah diproses sebelumnya.');
        }

        // Cek status order
        $order = $payment->order;
        if (!$order || $order->status !== 'menunggu_bayar') {
            return back()->with('error', 'Order tidak dalam status menunggu bayar.');
        }

        DB::beginTransaction();

        try {
            // Update payment
            $payment->update([
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            // Update order status
            $order->update(['status' => 'dikonfirmasi']);

            // Kurangi stok + catat stock movement
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

            return back()->with('success', "Pembayaran {$order->order_number} berhasil diverifikasi. Stok telah dikurangi.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal verifikasi: ' . $e->getMessage());
        }
    }

    /**
     * Reject pembayaran → status jadi "rejected" + order tetap "menunggu_bayar".
     */
    public function reject(Request $request, Payment $payment)
    {
        // Cek status payment
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Pembayaran ini sudah diproses sebelumnya.');
        }

        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $payment->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'note' => $validated['note'] ?? 'Ditolak oleh admin.',
        ]);

        return back()->with('success', 'Pembayaran ditolak. Customer akan diminta upload ulang.');
    }
}