<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::withCount('purchaseOrders')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . $request->search . '%')->orWhere('contact_name', 'like', '%' . $request->search . '%')->orWhere('phone', 'like', '%' . $request->search . '%')))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('admin.suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier ditambahkan.');
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return redirect()->route('admin.suppliers.index')->with('success', 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'Supplier ini sudah punya riwayat pembelian. Nonaktifkan saja, jangan dihapus.');
        }
        $supplier->delete();

        return back()->with('success', 'Supplier dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'contact_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:500',
            'note' => 'nullable|string|max:500',
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
