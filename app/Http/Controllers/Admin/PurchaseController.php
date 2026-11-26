<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseService;
use DomainException;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases)
    {
    }

    public function index(Request $request)
    {
        $status = $request->input('status', 'awaiting');
        $query = PurchaseOrder::with('supplier')->withCount('items')->withSum('items', 'qty_ordered')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('po_number', 'like', '%' . $request->search . '%')->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%' . $request->search . '%'))));

        if ($status === 'awaiting') {
            $query->awaiting();
        } elseif ($status !== 'all' && isset(PurchaseOrder::STATUSES[$status])) {
            $query->where('status', $status);
        }

        $counts = PurchaseOrder::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $stats = ['awaiting' => ($counts['ordered'] ?? 0) + ($counts['partial'] ?? 0), 'all' => $counts->sum()] + $counts->all();

        return view('admin.purchases.index', [
            'purchases' => $query->latest('id')->paginate(15)->withQueryString(),
            'status' => $status,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('admin.purchases.form', $this->formData(new PurchaseOrder(['shipping_cost' => 0]), []));
    }

    public function store(Request $request)
    {
        [$data, $items] = $this->validated($request);
        try {
            $po = $this->purchases->create($data, $items, auth()->id());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.show', $po->id)->with('success', "Pembelian {$po->po_number} disimpan sebagai draft.");
    }

    public function show(PurchaseOrder $purchase)
    {
        $purchase->load(['supplier', 'creator', 'items.product']);

        return view('admin.purchases.show', ['po' => $purchase]);
    }

    public function edit(PurchaseOrder $purchase)
    {
        if ($purchase->status !== 'draft') {
            return redirect()->route('admin.purchases.show', $purchase->id)->with('error', 'Hanya draft yang bisa diubah.');
        }
        $rows = $purchase->items->map(fn ($i) => ['product_id' => $i->product_id, 'qty' => $i->qty_ordered, 'unit_cost' => (float) $i->unit_cost])->all();

        return view('admin.purchases.form', $this->formData($purchase, $rows));
    }

    public function update(Request $request, PurchaseOrder $purchase)
    {
        [$data, $items] = $this->validated($request);
        try {
            $this->purchases->update($purchase, $data, $items);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.show', $purchase->id)->with('success', 'Draft diperbarui.');
    }

    public function order(PurchaseOrder $purchase)
    {
        return $this->act(fn () => $this->purchases->markOrdered($purchase), $purchase, 'Pembelian ditandai Dipesan.');
    }

    public function cancel(PurchaseOrder $purchase)
    {
        return $this->act(fn () => $this->purchases->cancel($purchase), $purchase, 'Pembelian dibatalkan.');
    }

    public function receive(Request $request, PurchaseOrder $purchase)
    {
        $received = (array) $request->input('received', []);
        try {
            $units = $this->purchases->receive($purchase, $received, auth()->id());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.show', $purchase->id)->with('success', "{$units} unit diterima. Stok dan HPP produk sudah diperbarui.");
    }

    private function act(callable $fn, PurchaseOrder $purchase, string $ok)
    {
        try {
            $fn();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.purchases.show', $purchase->id)->with('success', $ok);
    }

    private function formData(PurchaseOrder $po, array $rows): array
    {
        return [
            'po' => $po,
            'suppliers' => Supplier::where('is_active', true)->orWhere('id', $po->supplier_id)->orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'cost_price'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'cost' => $p->cost_price !== null ? (float) $p->cost_price : null])->values(),
            'rows' => old('items') ? array_values(old('items')) : $rows,
        ];
    }

    /** @return array{0:array,1:array} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|exists:suppliers,id',
            'expected_at' => 'nullable|date',
            'shipping_cost' => 'nullable|numeric|min:0|max:100000000000',
            'note' => 'nullable|string|max:500',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1|max:1000000',
            'items.*.unit_cost' => 'required|numeric|min:0|max:100000000000',
        ], [
            'items.required' => 'Tambahkan minimal satu produk.',
            'items.*.qty.min' => 'Jumlah minimal 1.',
        ]);

        return [$data, $data['items']];
    }
}
