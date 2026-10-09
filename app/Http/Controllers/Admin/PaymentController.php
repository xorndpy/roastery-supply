<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderService;
use DomainException;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

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
     * Verify pembayaran -> order "dikonfirmasi" + stok berkurang (lihat OrderService).
     */
    public function verify(Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Pembayaran ini sudah diproses sebelumnya.');
        }

        try {
            $this->orders->confirmPayment($payment->order, auth()->id(), $payment);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pembayaran {$payment->order->order_number} berhasil diverifikasi. Stok telah dikurangi.");
    }

    /**
     * Reject pembayaran. Order tetap "menunggu_bayar" dan customer bisa upload ulang.
     */
    public function reject(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $this->orders->rejectPayment($payment, $validated['note'] ?? null, auth()->id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran ditolak. Customer akan diminta upload ulang.');
    }
}
