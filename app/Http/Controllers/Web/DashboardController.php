<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\VoucherService;

class DashboardController extends Controller
{
    public function index(VoucherService $vouchers)
    {
        $user = auth()->user();

        $counts = Order::where('user_id', $user->id)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $stats = [
            'total' => (int) $counts->except('batal')->sum(),
            'done' => (int) ($counts['selesai'] ?? 0),
            'waiting' => (int) ($counts['menunggu_bayar'] ?? 0),
        ];

        // Satu "tindakan berikutnya" yang paling penting: bayar dulu (yang paling dekat batas waktu), lalu lacak pengiriman.
        $pending = Order::where('user_id', $user->id)->where('status', 'menunggu_bayar')->with('payments')->oldest()->first();
        $shipping = $pending ? null : Order::where('user_id', $user->id)->where('status', 'dikirim')->latest()->first();

        $action = null;
        if ($pending) {
            $action = [
                'type' => 'pay',
                'order' => $pending,
                'deadline' => $pending->created_at->copy()->addHours(OrderService::PAYMENT_WINDOW_HOURS),
                // bukti sudah diunggah / pembayaran sedang ditinjau: jangan suruh bayar lagi
                'reviewing' => $pending->payments->contains(fn ($p) => in_array($p->status, ['pending', 'review'], true)),
                'others' => max(0, $stats['waiting'] - 1),
            ];
        } elseif ($shipping) {
            $action = ['type' => 'track', 'order' => $shipping];
        }

        return view('customer.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'action' => $action,
            'recentOrders' => Order::where('user_id', $user->id)->with('items.product.images')->latest()->take(4)->get(),
            'vouchers' => $vouchers->availableFor($user, null, 3),
            'isNewMember' => ! $vouchers->hasOrdered($user),
        ]);
    }
}
