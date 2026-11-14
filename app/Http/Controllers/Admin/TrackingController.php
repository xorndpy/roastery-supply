<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TrackingService;

class TrackingController extends Controller
{
    public function sync(Order $order, TrackingService $tracking)
    {
        $shipment = $order->shipment;
        if (! $shipment || ! $shipment->tracking_number) {
            return back()->with('error', 'Pesanan ini belum punya nomor resi.');
        }
        if (! $tracking->enabled()) {
            return back()->with('error', 'Pelacakan otomatis belum aktif (RAJAONGKIR_API_KEY kosong).');
        }
        if (! TrackingService::courierCode($shipment->courier)) {
            return back()->with('error', "Kurir {$shipment->courier} belum didukung pelacakan otomatis.");
        }

        $ok = $tracking->sync($shipment);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Status resi diperbarui.' : 'Resi belum ditemukan di sistem kurir. Coba lagi nanti.');
    }
}
