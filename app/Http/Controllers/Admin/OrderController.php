<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

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
     * Konfirmasi pembayaran -> "dikonfirmasi" + stok berkurang (lihat OrderService).
     */
    public function confirmPayment(Order $order)
    {
        return $this->run(
            fn () => $this->orders->confirmPayment($order, auth()->id()),
            'Pembayaran dikonfirmasi. Stok telah dikurangi.'
        );
    }

    public function process(Order $order)
    {
        return $this->run(fn () => $this->orders->process($order), 'Pesanan sedang diproses.');
    }

    /**
     * Input resi & kirim pesanan.
     */
    public function ship(Request $request, Order $order)
    {
        $validated = $request->validate([
            'courier' => 'required|string|max:100',
            'service' => 'nullable|string|max:100',
            'tracking_number' => 'required|string|max:100',
            'cost' => 'nullable|numeric|min:0',
        ]);

        return $this->run(
            fn () => $this->orders->ship($order, $validated),
            'Resi berhasil diinput. Pesanan sedang dikirim.'
        );
    }

    public function complete(Order $order)
    {
        return $this->run(fn () => $this->orders->complete($order), 'Pesanan selesai.');
    }

    /**
     * Batalkan pesanan (admin: sampai status diproses). Stok kembali jika sudah dipotong.
     */
    public function cancel(Request $request, Order $order)
    {
        $reason = $request->validate(['reason' => 'nullable|string|max:255'])['reason'] ?? null;

        return $this->run(
            fn () => $this->orders->cancel($order, $reason, auth()->id()),
            'Pesanan dibatalkan.'
        );
    }

    /**
     * Invoice PDF (PRD 6.2).
     */
    public function invoice(Order $order)
    {
        $order->load(['customer', 'items', 'payment']);

        return Pdf::loadView('pdf.invoice', compact('order'))
            ->setPaper('a4')
            ->stream('invoice-' . $order->order_number . '.pdf');
    }

    private function run(\Closure $action, string $successMessage)
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $successMessage);
    }
}
