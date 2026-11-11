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
     * Gudang: simpan kurir & resi (belum mengirim). Status tetap "diproses".
     */
    public function prepareShipment(Request $request, Order $order)
    {
        $validated = $this->shipFields($request, $order);

        return $this->run(
            fn () => $this->orders->prepareShipment($order, $validated),
            'Kurir & resi tersimpan. Menunggu admin menekan Kirim.'
        );
    }

    /**
     * Admin: kirim pesanan. Hanya setelah gudang mengemas (diproses). Kurir & resi diambil dari isian gudang,
     * kecuali admin mengirim data baru dari formulir.
     */
    public function ship(Request $request, Order $order)
    {
        if ($order->status !== 'diproses') {
            return back()->with('error', 'Pesanan belum dikemas gudang. Gudang perlu menandai dikemas dan mengisi resi dulu.');
        }

        $draft = $order->shipment;
        if (!$request->filled('tracking_number')) {
            if (!$draft || !$draft->tracking_number) {
                return back()->with('error', 'Gudang belum mengisi kurir & resi.');
            }
            $request->merge(['courier' => $draft->courier, 'service' => $draft->service, 'tracking_number' => $draft->tracking_number]);
        }

        $validated = $this->shipFields($request, $order);

        return $this->run(
            fn () => $this->orders->ship($order, $validated),
            'Pesanan dikirim. Pelanggan sudah diberi tahu.'
        );
    }

    /** Validasi & rapikan isian kurir/resi (dipakai gudang dan admin). */
    private function shipFields(Request $request, Order $order): array
    {
        $validated = $request->validate([
            'courier' => 'required|string|max:100',
            'courier_other' => 'nullable|string|max:100|required_if:courier,__other',
            'service' => 'nullable|string|max:100',
            'tracking_number' => 'required|string|max:100',
            'cost' => 'nullable|numeric|min:0',
        ], [
            'courier.required' => 'Pilih kurir.',
            'courier_other.required_if' => 'Isi nama kurir.',
            'tracking_number.required' => 'Nomor resi wajib diisi.',
        ]);

        // "Lainnya" -> pakai nama yang diketik; resi dirapikan (huruf besar, tanpa spasi) lalu dicek.
        $validated['courier'] = $validated['courier'] === '__other' ? trim($validated['courier_other']) : $validated['courier'];
        unset($validated['courier_other']);
        $validated['tracking_number'] = $this->cleanResi($validated['courier'], $validated['tracking_number'], $order->id);

        return $validated;
    }

    /** Rapikan & periksa nomor resi: format wajar dan belum dipakai pesanan lain. */
    private function cleanResi(string $courier, string $resi, ?int $orderId): string
    {
        $resi = \App\Support\Couriers::normalizeResi($resi);

        if (! \App\Support\Couriers::validResi($resi)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tracking_number' => 'Nomor resi tidak valid. Gunakan huruf/angka saja (4-40 karakter), tanpa spasi.',
            ]);
        }
        if ($used = \App\Support\Couriers::usedByOtherOrder($courier, $resi, $orderId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tracking_number' => "Resi ini sudah dipakai di pesanan {$used}. Periksa lagi agar tidak tertukar.",
            ]);
        }

        return $resi;
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

    /** Surat jalan / packing list tanpa harga, untuk gudang. Hanya pesanan yang sudah dibayar. */
    public function deliveryNote(Order $order)
    {
        if (!in_array($order->status, ['dikonfirmasi', 'diproses', 'dikirim', 'selesai'], true)) {
            return back()->with('error', 'Surat jalan baru tersedia setelah pembayaran dikonfirmasi.');
        }
        $order->load(['customer', 'items']);

        return Pdf::loadView('pdf.surat-jalan', compact('order'))
            ->setPaper('a4')
            ->stream('surat-jalan-' . $order->order_number . '.pdf');
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
