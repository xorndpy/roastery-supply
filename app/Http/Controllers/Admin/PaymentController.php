<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Payments\PaymentReconciler;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $query = Payment::with(['order.customer', 'verifier']);

        // Tab: perhatian | menunggu | otomatis | manual | semua
        $tab = $request->query('tab', $request->status ? 'semua' : 'perhatian');
        match ($tab) {
            'perhatian' => $query->needsAttention(),
            'menunggu'  => $query->where('status', 'waiting'),
            'otomatis'  => $query->gateway()->where('status', 'verified')->whereNull('verified_by'),
            'manual'    => $query->whereNull('provider'),
            default     => null,
        };

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($cq) use ($request) {
                      $cq->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $payments = $query->latest('id')->paginate(15)->withQueryString();

        $counts = [
            'perhatian' => Payment::needsAttention()->count(),
            'menunggu'  => Payment::where('status', 'waiting')->count(),
            'otomatis'  => Payment::gateway()->where('status', 'verified')->whereNull('verified_by')->count(),
            'manual'    => Payment::whereNull('provider')->count(),
            'semua'     => Payment::count(),
        ];

        $autoThisWeek = Payment::gateway()->where('status', 'verified')->whereNull('verified_by')
            ->where('verified_at', '>=', now()->subDays(7))->count();

        return view('admin.payments.index', compact('payments', 'counts', 'tab', 'autoThisWeek'));
    }

    /**
     * Detail pembayaran.
     */
    public function show(Payment $payment)
    {
        $payment->load(['order.customer', 'order.items.product', 'verifier']);
        $events = \App\Models\PaymentEvent::where('provider_order_id', $payment->provider_order_id)
            ->whereNotNull('provider_order_id')->orderBy('id')->get();

        return view('admin.payments.show', compact('payment', 'events'));
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

    /**
     * Tanyakan status terkini ke gateway dan proses sekarang juga (lewat jalur yang sama dengan webhook).
     */
    public function sync(Payment $payment, PaymentReconciler $reconciler)
    {
        if (!$payment->isGateway()) {
            return back()->with('error', 'Pembayaran manual tidak punya status di gateway.');
        }

        $result = $reconciler->check($payment, sync: true);

        return back()->with(
            $result === 'gagal_cek' ? 'error' : 'success',
            match ($result) {
                'gagal_cek'      => 'Gagal menghubungi Midtrans. Coba lagi sebentar.',
                'belum_ada'      => 'Midtrans belum menerima transaksi ini (pelanggan belum memilih metode bayar).',
                'kedaluwarsa'    => 'Sesi pembayaran sudah berakhir tanpa pembayaran.',
                'sudah_diproses' => 'Status di Midtrans sudah pernah diproses; tidak ada perubahan.',
                default          => 'Status dari Midtrans sudah diperbarui.',
            },
        );
    }

    /**
     * Setujui pembayaran gateway yang ditahan (mis. stok kini sudah ditambah). Tetap lewat OrderService.
     */
    public function approve(Payment $payment)
    {
        if (!$payment->isGateway() || $payment->status !== 'review') {
            return back()->with('error', 'Hanya pembayaran gateway berstatus "Perlu ditinjau" yang bisa disetujui.');
        }

        try {
            $r = $this->orders->settleGatewayPayment($payment->id, [], auth()->id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $r['outcome'] === 'dikonfirmasi'
            ? back()->with('success', 'Pembayaran disetujui. Pesanan dikonfirmasi dan stok dikurangi.')
            : back()->with('error', 'Belum bisa disetujui: ' . ($r['reason'] ? (Payment::REASONS[$r['reason']][0] ?? $r['reason']) : $r['outcome']) . '. Periksa penjelasan di halaman ini.');
    }

    /**
     * Tutup pengecualian: "sudah direfund" (uang dikembalikan lewat dashboard Midtrans) atau "selesai ditinjau".
     * Opsional membatalkan pesanan sekaligus bila masih menunggu pembayaran.
     */
    public function resolve(Request $request, Payment $payment)
    {
        $data = $request->validate([
            'action'       => 'required|in:refunded,dismiss',
            'note'         => 'nullable|string|max:500',
            'cancel_order' => 'nullable|boolean',
        ]);

        if ($payment->review_reason === null && $payment->status !== 'review') {
            return back()->with('error', 'Pembayaran ini tidak sedang memerlukan tindakan.');
        }

        DB::transaction(function () use ($payment, $data) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $attrs = ['reviewed_at' => now(), 'verified_by' => $p->verified_by ?? auth()->id()];
            if ($data['action'] === 'refunded' && in_array($p->status, ['review', 'duplicate'], true)) {
                $attrs['status'] = 'refunded';
            }
            if (!empty($data['note'])) {
                $attrs['note'] = trim(($p->note ? $p->note . "\n" : '') . 'Admin: ' . $data['note']);
            }
            $p->update($attrs);
        });

        $msg = $data['action'] === 'refunded' ? 'Ditandai sudah direfund.' : 'Ditandai selesai ditinjau.';

        if (!empty($data['cancel_order'])) {
            try {
                $this->orders->cancel($payment->order, 'Dibatalkan admin setelah pengecualian pembayaran.', auth()->id(), ['menunggu_bayar']);
                $msg .= ' Pesanan dibatalkan.';
            } catch (DomainException $e) {
                return back()->with('success', $msg)->with('error', 'Pesanan tidak dibatalkan: ' . $e->getMessage());
            }
        }

        return back()->with('success', $msg);
    }
}
