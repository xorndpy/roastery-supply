<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ReturnOrder;
use App\Models\Order;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function index()
    {
        $returns = ReturnOrder::whereHas('order', function ($q) {
            $q->where('user_id', auth()->id());
        })->with('order')->latest()->paginate(10);

        return view('customer.returns.index', compact('returns'));
    }

    public function create($orderNumber = null)
    {
        $order = null;
        if ($orderNumber) {
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', auth()->id())
                ->firstOrFail();
        }

        return view('customer.returns.create', compact('order'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        ReturnOrder::create([
            'order_id' => $order->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return redirect()->route('customer.returns.index')
            ->with('success', 'Pengajuan retur berhasil dikirim.');
    }

    public function show($id)
    {
        $return = ReturnOrder::whereHas('order', function ($q) {
            $q->where('user_id', auth()->id());
        })->with('order')->findOrFail($id);

        return view('customer.returns.show', compact('return'));
    }
}