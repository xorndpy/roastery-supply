<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    /**
     * Antrean pengiriman: perlu dikirim / dalam perjalanan / sudah sampai.
     */
    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['ready', 'shipped', 'delivered'], true) ? $request->tab : 'ready';

        $counts = [
            'ready'     => Order::whereIn('status', ['dikonfirmasi', 'diproses'])->count(),
            'shipped'   => Shipment::where('status', 'shipped')->count(),
            'delivered' => Shipment::where('status', 'delivered')->count(),
        ];

        if ($tab === 'ready') {
            $orders = Order::with(['customer', 'items'])
                ->whereIn('status', ['dikonfirmasi', 'diproses'])
                ->when($request->search, fn ($q, $s) => $q->where('order_number', 'like', "%{$s}%"))
                ->oldest()
                ->paginate(20)
                ->withQueryString();

            return view('admin.shipments.index', ['tab' => $tab, 'counts' => $counts, 'orders' => $orders, 'shipments' => null]);
        }

        $shipments = Shipment::with('order.customer')
            ->where('status', $tab)
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($w) => $w->where('tracking_number', 'like', "%{$s}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$s}%")));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.shipments.index', ['tab' => $tab, 'counts' => $counts, 'orders' => null, 'shipments' => $shipments]);
    }

    /**
     * Koreksi kurir/resi (salah ketik). Perubahan status tetap lewat Order.
     */
    public function update(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'courier'         => 'required|string|max:100',
            'service'         => 'nullable|string|max:100',
            'tracking_number' => 'required|string|max:100',
        ]);

        $shipment->update($validated);

        return back()->with('success', 'Data pengiriman diperbarui.');
    }
}
