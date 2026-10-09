<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnOrder;
use App\Services\ReturnService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns)
    {
    }

    public function index()
    {
        $returns = ReturnOrder::whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('order')->latest('id')->paginate(10);

        return view('customer.returns.index', compact('returns'));
    }

    /** Form pengajuan. Tanpa nomor order: tampilkan pesanan milik user yang masih bisa diretur. */
    public function create(?string $orderNumber = null)
    {
        if ($orderNumber) {
            $order = $this->ownOrder($orderNumber);

            if ($reason = $this->returns->ineligibleReason($order)) {
                return redirect()->route('customer.orders.show', $order->order_number)->with('error', $reason);
            }

            return view('customer.returns.create', ['order' => $order, 'eligible' => collect(), 'reasonTypes' => ReturnOrder::REASON_TYPES]);
        }

        $eligible = Order::where('user_id', auth()->id())->where('status', 'selesai')->with('items')->latest('id')->get()
            ->filter(fn ($o) => $this->returns->ineligibleReason($o) === null)->values();

        return view('customer.returns.create', ['order' => null, 'eligible' => $eligible, 'reasonTypes' => ReturnOrder::REASON_TYPES]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_number'          => 'required|string',
            'reason_type'           => ['required', Rule::in(array_keys(ReturnOrder::REASON_TYPES))],
            'reason'                => 'required|string|min:10|max:1000',
            'photos'                => 'required|array|min:1|max:3',
            'photos.*'              => 'image|mimes:jpg,jpeg,png,webp|max:4096',
            'refund_bank'           => 'required|string|max:50',
            'refund_account_number' => ['required', 'regex:/^[0-9]{5,30}$/'],
            'refund_account_name'   => 'required|string|max:100',
        ], [
            'photos.required' => 'Sertakan minimal 1 foto bukti.',
            'photos.max' => 'Maksimal 3 foto.',
            'refund_account_number.regex' => 'Nomor rekening hanya angka (5-30 digit).',
        ]);

        $order = $this->ownOrder($validated['order_number']);

        // Cek di depan agar file tidak perlu diunggah kalau memang tidak berhak.
        if ($reason = $this->returns->ineligibleReason($order)) {
            return back()->withInput()->with('error', $reason);
        }

        $paths = [];
        foreach ($request->file('photos') as $photo) {
            $paths[] = $photo->store('returns', 'local'); // privat; dibuka lewat ReturnEvidenceController
        }

        try {
            $return = $this->returns->submit($order, collect($validated)->only(['reason_type', 'reason', 'refund_bank', 'refund_account_number', 'refund_account_name'])->all(), $paths);
        } catch (DomainException $e) {
            Storage::disk('local')->delete($paths);

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.returns.show', $return->id)
            ->with('success', 'Pengajuan retur terkirim. Kami akan meninjau dan mengabari lewat email.');
    }

    public function show($id)
    {
        $return = ReturnOrder::whereHas('order', fn ($q) => $q->where('user_id', auth()->id()))
            ->with('order.items')->findOrFail($id);

        return view('customer.returns.show', compact('return'));
    }

    private function ownOrder(string $orderNumber): Order
    {
        return Order::where('order_number', $orderNumber)->where('user_id', auth()->id())->firstOrFail();
    }
}
