<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = Voucher::query()->latest('id');

        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('code', 'like', '%' . strtoupper($search) . '%')->orWhere('description', 'like', "%{$search}%"));
        }

        $vouchers = $query->get();

        if ($request->filled('state')) {
            $vouchers = $vouchers->where('state', $request->state)->values();
        }

        $counts = Voucher::all()->groupBy('state')->map->count();

        return view('admin.vouchers.index', compact('vouchers', 'counts'));
    }

    public function create()
    {
        return view('admin.vouchers.form', ['voucher' => new Voucher(['type' => 'percentage', 'is_active' => true, 'per_user_limit' => 1, 'quota' => 0, 'min_purchase' => 0])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data['code'] = VoucherService::normalize($data['code']);

        Voucher::create($data);

        return redirect()->route('admin.vouchers.index')->with('success', "Voucher {$data['code']} dibuat.");
    }

    public function edit(Voucher $voucher)
    {
        return view('admin.vouchers.form', compact('voucher'));
    }

    public function update(Request $request, Voucher $voucher)
    {
        $data = $this->validated($request, $voucher);

        // Kode & jenis diskon dikunci setelah pernah dipakai: riwayat order tetap konsisten.
        if ($voucher->used_count > 0) {
            unset($data['code'], $data['type']);
        } else {
            $data['code'] = VoucherService::normalize($data['code']);
        }

        $voucher->update($data);

        return redirect()->route('admin.vouchers.index')->with('success', 'Voucher diperbarui.');
    }

    public function destroy(Voucher $voucher)
    {
        // Soft delete: order lama tetap menyimpan voucher_code & nominal diskonnya.
        $voucher->delete();

        return back()->with('success', 'Voucher dihapus.');
    }

    private function validated(Request $request, ?Voucher $voucher): array
    {
        // Normalisasi dulu agar "hemat 10 " dan "HEMAT10" dianggap kode yang sama saat cek unik.
        $request->merge(['code' => VoucherService::normalize((string) $request->input('code'))]);
        $type = $request->input('type');

        $data = $request->validate([
            'code'           => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vouchers', 'code')->ignore($voucher?->id)],
            'description'    => 'nullable|string|max:255',
            'type'           => ['required', Rule::in(['percentage', 'fixed'])],
            'value'          => $type === 'percentage' ? 'required|numeric|min:1|max:100' : 'required|numeric|min:1|max:1000000000',
            'min_purchase'   => 'nullable|numeric|min:0|max:1000000000',
            'max_discount'   => 'nullable|numeric|min:0|max:1000000000',
            'quota'          => 'nullable|integer|min:0|max:1000000',
            'per_user_limit' => 'nullable|integer|min:0|max:1000',
            'start_at'       => 'nullable|date',
            'end_at'         => 'nullable|date|after:start_at',
        ], [
            'code.regex' => 'Kode hanya huruf, angka, strip (-) dan garis bawah (_).',
            'value.max'  => $type === 'percentage' ? 'Persentase maksimal 100.' : 'Nominal terlalu besar.',
        ]);

        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['quota'] = $data['quota'] ?? 0;
        $data['per_user_limit'] = $data['per_user_limit'] ?? 1;
        // Batas diskon hanya bermakna untuk tipe persen.
        $data['max_discount'] = $data['type'] === 'percentage' && ($data['max_discount'] ?? 0) > 0 ? $data['max_discount'] : null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
