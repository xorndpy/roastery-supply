<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Daftar garansi per unit: isi nomor seri, batalkan garansi (mis. salah kirim/penyalahgunaan). */
class WarrantyController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'in:active,expired,void,noserial']]);

        $q = Warranty::query()->with('user:id,name', 'order:id,order_number');
        $today = now('Asia/Jakarta')->toDateString();

        match ($request->query('state')) {
            'active' => $q->where('status', 'active')->where('expires_at', '>=', $today),
            'expired' => $q->where('status', 'active')->where('expires_at', '<', $today),
            'void' => $q->where('status', 'void'),
            'noserial' => $q->whereNull('serial_number')->where('status', 'active'),
            default => null,
        };
        if ($s = trim((string) $request->query('search'))) {
            $like = '%'.addcslashes($s, '%_\\').'%';
            $q->where(fn ($w) => $w->where('serial_number', 'like', $like)->orWhere('product_name', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like))->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)));
        }

        return view('admin.warranties.index', ['warranties' => $q->orderByDesc('id')->paginate(25)->withQueryString()]);
    }

    public function update(Request $request, Warranty $warranty)
    {
        $data = $request->validate([
            'serial_number' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9\-_\/.]+$/', Rule::unique('warranties', 'serial_number')->ignore($warranty->id)],
        ], ['serial_number.unique' => 'Nomor seri ini sudah terdaftar di garansi lain.', 'serial_number.regex' => 'Nomor seri hanya boleh huruf, angka, dan - _ / .']);

        $warranty->update(['serial_number' => $data['serial_number'] ?: null]);

        return back()->with('success', 'Nomor seri disimpan.');
    }

    public function void(Request $request, Warranty $warranty)
    {
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:200']], ['void_reason.required' => 'Alasan pembatalan wajib diisi.']);
        $warranty->update(['status' => 'void', 'void_reason' => $data['void_reason']]);

        return back()->with('success', 'Garansi dibatalkan.');
    }
}
