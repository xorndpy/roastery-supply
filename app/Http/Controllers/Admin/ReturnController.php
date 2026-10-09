<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnOrder;
use App\Services\ReturnService;
use DomainException;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns)
    {
    }

    public function index(Request $request)
    {
        $tab = in_array($request->tab, ['pending', 'approved', 'refunded', 'rejected', 'all'], true) ? $request->tab : 'pending';

        $counts = [
            'pending'  => ReturnOrder::where('status', 'pending')->count(),
            'approved' => ReturnOrder::where('status', 'approved')->whereNull('refunded_at')->count(),
            'refunded' => ReturnOrder::where('status', 'approved')->whereNotNull('refunded_at')->count(),
            'rejected' => ReturnOrder::where('status', 'rejected')->count(),
            'all'      => ReturnOrder::count(),
        ];

        $query = ReturnOrder::with('order.customer')->latest('id');

        match ($tab) {
            'pending'  => $query->where('status', 'pending'),
            'approved' => $query->where('status', 'approved')->whereNull('refunded_at'),
            'refunded' => $query->where('status', 'approved')->whereNotNull('refunded_at'),
            'rejected' => $query->where('status', 'rejected'),
            default    => null,
        };

        if ($search = $request->search) {
            $query->whereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$search}%")
                ->orWhere('shipping_name', 'like', "%{$search}%"));
        }

        $returns = $query->paginate(20)->withQueryString();

        return view('admin.returns.index', compact('returns', 'counts', 'tab'));
    }

    public function show(ReturnOrder $returnOrder)
    {
        $returnOrder->load(['order.customer', 'order.items', 'order.payment', 'approvedBy']);

        return view('admin.returns.show', ['return' => $returnOrder]);
    }

    public function approve(Request $request, ReturnOrder $returnOrder)
    {
        $data = $request->validate([
            'refund_amount' => 'nullable|numeric|min:0|max:1000000000',
            'note'          => 'nullable|string|max:500',
        ]);

        return $this->run(
            fn () => $this->returns->approve(
                $returnOrder, auth()->id(),
                isset($data['refund_amount']) && $data['refund_amount'] !== '' ? (float) $data['refund_amount'] : null,
                $request->boolean('restock'),
                $data['note'] ?? null
            ),
            'Retur disetujui. Silakan transfer refund ke customer lalu tandai selesai.'
        );
    }

    public function reject(Request $request, ReturnOrder $returnOrder)
    {
        $data = $request->validate(['note' => 'required|string|max:500'], ['note.required' => 'Alasan penolakan wajib diisi (dilihat customer).']);

        return $this->run(fn () => $this->returns->reject($returnOrder, auth()->id(), $data['note']), 'Retur ditolak.');
    }

    public function refunded(ReturnOrder $returnOrder)
    {
        return $this->run(fn () => $this->returns->markRefunded($returnOrder), 'Refund ditandai selesai.');
    }

    private function run(\Closure $action, string $success)
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
