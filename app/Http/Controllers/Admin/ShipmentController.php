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
            $orders = Order::with(['customer', 'items', 'shipment'])
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

        $resi = \App\Support\Couriers::normalizeResi($validated['tracking_number']);
        if (! \App\Support\Couriers::validResi($resi)) {
            return back()->withErrors(['tracking_number' => 'Nomor resi tidak valid. Gunakan huruf/angka saja (4-40 karakter).'])->withInput();
        }
        if ($used = \App\Support\Couriers::usedByOtherOrder($validated['courier'], $resi, $shipment->order_id)) {
            return back()->withErrors(['tracking_number' => "Resi ini sudah dipakai di pesanan {$used}."])->withInput();
        }
        $validated['tracking_number'] = $resi;

        $shipment->update($validated);

        return back()->with('success', 'Data pengiriman diperbarui.');
    }
}
