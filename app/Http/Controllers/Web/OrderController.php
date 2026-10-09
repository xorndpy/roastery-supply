<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

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
        $order = $this->findOwn($orderNumber, ['items.product', 'payment', 'shipment', 'return', 'testimonial']);

        $returnEligible = $order->status === 'selesai' && !$order->return
            && app(\App\Services\ReturnService::class)->ineligibleReason($order) === null;

        return view('customer.orders.show', compact('order', 'returnEligible'));
    }

    /**
     * Upload bukti pembayaran. Jumlah selalu = total order (tidak dipercaya dari form).
     * Upload ulang diizinkan jika bukti sebelumnya ditolak, atau masih menunggu (diganti).
     */
    public function pay(Request $request, $orderNumber)
    {
        $order = $this->findOwn($orderNumber);

        if ($order->status !== 'menunggu_bayar') {
            return back()->with('error', 'Pesanan ini sudah tidak menunggu pembayaran.');
        }

        $validated = $request->validate([
            'method' => 'required|string|max:50',
            'proof_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'note' => 'nullable|string|max:500',
        ]);

        // Disk 'local' = privat (storage/app/private); dibuka lewat PaymentProofController.
        $path = $request->file('proof_image')->store('payments', 'local');

        try {
            DB::transaction(function () use ($order, $validated, $path) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($order->status !== 'menunggu_bayar') {
                    throw new DomainException('Pesanan ini sudah tidak menunggu pembayaran.');
                }

                $pending = $order->payments()->where('status', 'pending')->lockForUpdate()->first();

                $attributes = [
                    'method' => $validated['method'],
                    'amount' => $order->total,
                    'proof_image' => $path,
                    'status' => 'pending',
                    'note' => $validated['note'] ?? null,
                ];

                if ($pending) {
                    $old = $pending->proof_image;
                    $pending->update($attributes);
                    if ($old) {
                        $this->deleteProof($old);
                    }
                } else {
                    // Bukti yang ditolak tetap tersimpan sebagai riwayat.
                    $order->payments()->create($attributes);
                }
            });
        } catch (DomainException $e) {
            $this->deleteProof($path);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Bukti pembayaran berhasil diunggah. Menunggu verifikasi admin.');
    }

    /**
     * Customer membatalkan pesanan sendiri (hanya sebelum bayar diverifikasi).
     */
    public function cancel($orderNumber)
    {
        $order = $this->findOwn($orderNumber);

        try {
            $this->orders->cancel($order, 'Dibatalkan oleh customer.', auth()->id(), ['menunggu_bayar']);
        } catch (DomainException) {
            return back()->with('error', 'Pesanan yang sudah dibayar tidak bisa dibatalkan sendiri. Hubungi admin.');
        }

        return back()->with('success', 'Pesanan dibatalkan.');
    }

    /**
     * Customer konfirmasi barang diterima -> selesai.
     */
    public function received($orderNumber)
    {
        $order = $this->findOwn($orderNumber);

        try {
            $this->orders->complete($order);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Terima kasih! Pesanan ditandai selesai.');
    }

    public function invoice($orderNumber)
    {
        $order = $this->findOwn($orderNumber, ['customer', 'items', 'payment']);

        return Pdf::loadView('pdf.invoice', compact('order'))
            ->setPaper('a4')
            ->stream('invoice-' . $order->order_number . '.pdf');
    }

    private function deleteProof(string $path): void
    {
        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path); // sisa file lama sebelum migrasi privat
    }

    private function findOwn(string $orderNumber, array $with = []): Order
    {
        return Order::where('order_number', $orderNumber)
            ->where('user_id', auth()->id())
            ->with($with)
            ->firstOrFail();
    }
}
