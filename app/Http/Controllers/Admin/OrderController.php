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
     * Query daftar pesanan dengan seluruh filter (dipakai halaman daftar dan export).
     */
    protected function filteredQuery(Request $request)
    {
        $query = Order::with(['customer', 'items', 'payment', 'shipment']);

        if ($request->filled('status')) {
            $request->status === 'terlambat' ? $query->late() : $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', $term)
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // Status pembayaran: belum_bayar = belum ada pembayaran sama sekali
        if ($request->filled('payment_status')) {
            $request->payment_status === 'belum_bayar'
                ? $query->whereDoesntHave('payments')
                : $query->whereHas('payments', fn ($p) => $p->where('status', $request->payment_status));
        }

        // Metode pembayaran: midtrans (gateway) atau kode transfer manual
        if ($request->filled('payment_method')) {
            $request->payment_method === 'midtrans'
                ? $query->whereHas('payments', fn ($p) => $p->whereNotNull('provider'))
                : $query->whereHas('payments', fn ($p) => $p->whereNull('provider')->where('method', $request->payment_method));
        }

        if ($request->filled('courier')) {
            $query->whereHas('shipment', fn ($s) => $s->where('courier', $request->courier));
        }

        // Rentang nominal hanya untuk yang boleh melihat keuangan
        if (auth()->user()?->can('view_finance')) {
            if ($request->filled('min_total')) {
                $query->where('total', '>=', (float) $request->min_total);
            }
            if ($request->filled('max_total')) {
                $query->where('total', '<=', (float) $request->max_total);
            }
        }

        $sortable = ['created_at', 'total', 'order_number', 'status'];
        $sort = in_array($request->sort, $sortable, true) ? $request->sort : 'created_at';
        if ($sort === 'total' && !auth()->user()?->can('view_finance')) {
            $sort = 'created_at';
        }
        $dir = $request->dir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $dir)->orderByDesc('id');
    }

    /**
     * List pesanan dengan filter, sortir, dan pilihan jumlah data.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->per_page;
        $perPage = in_array($perPage, config('orders.per_page_options'), true) ? $perPage : 10;

        $orders = $this->filteredQuery($request)->paginate($perPage)->withQueryString();

        $stats = [
            'all' => Order::count(),
            'menunggu_bayar' => Order::where('status', 'menunggu_bayar')->count(),
            'dikonfirmasi' => Order::where('status', 'dikonfirmasi')->count(),
            'diproses' => Order::where('status', 'diproses')->count(),
            'dikirim' => Order::where('status', 'dikirim')->count(),
            'selesai' => Order::where('status', 'selesai')->count(),
            'batal' => Order::where('status', 'batal')->count(),
            'terlambat' => Order::late()->count(),
        ];

        $couriers = \App\Models\Shipment::whereNotNull('courier')->distinct()->orderBy('courier')->pluck('courier');
        $paymentMethods = ['midtrans' => 'Midtrans', 'transfer_bca' => 'Transfer BCA', 'transfer_mandiri' => 'Transfer Mandiri', 'transfer_bni' => 'Transfer BNI'];

        return view('admin.orders.index', compact('orders', 'stats', 'couriers', 'paymentMethods'));
    }

    /**
     * Aksi massal: proses, selesaikan, atau batalkan banyak pesanan sekaligus.
     * Setiap pesanan dijalankan lewat OrderService, yang gagal dilaporkan terpisah.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
            'action' => 'required|in:process,complete,cancel',
            'reason' => 'nullable|string|max:255',
        ]);

        $permission = ['process' => 'process_orders', 'complete' => 'complete_orders', 'cancel' => 'cancel_orders'][$data['action']];
        abort_unless($request->user()->can($permission), 403);

        $ok = 0;
        $failed = [];
        foreach (Order::whereIn('id', $data['ids'])->get() as $order) {
            try {
                match ($data['action']) {
                    'process' => $this->orders->process($order),
                    'complete' => $this->orders->complete($order),
                    'cancel' => $this->orders->cancel($order, $data['reason'] ?? 'Dibatalkan massal', auth()->id()),
                };
                $ok++;
            } catch (DomainException $e) {
                $failed[] = $order->order_number . ' (' . $e->getMessage() . ')';
            }
        }

        $msg = "{$ok} pesanan berhasil diproses.";
        if ($failed) {
            return back()->with('success', $ok ? $msg : null)->with('error', count($failed) . ' gagal: ' . implode('; ', array_slice($failed, 0, 5)));
        }

        return back()->with('success', $msg);
    }

    /**
     * Export daftar pesanan sesuai filter yang sedang aktif.
     */
    public function export(Request $request)
    {
        $orders = $this->filteredQuery($request)->limit(5000)->get();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\OrdersExport($orders, auth()->user()->can('view_finance')),
            'pesanan-' . now('Asia/Jakarta')->format('Ymd-His') . '.xlsx'
        );
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

        return $this->run(
            fn () => $this->orders->ship($order, $validated),
            'Resi berhasil diinput. Pesanan sedang dikirim.'
        );
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
