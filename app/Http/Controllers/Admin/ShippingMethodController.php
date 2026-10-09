<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Layanan pengiriman + tarif per wilayah. Ongkir = max(tarif minimum, tarif per kg x kg tagihan);
 * tarif kota mengalahkan tarif provinsi. Lihat App\Services\ShippingService.
 */
class ShippingMethodController extends Controller
{
    public function index()
    {
        $methods = ShippingMethod::withCount('rates')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.shipping.index', compact('methods'));
    }

    public function create()
    {
        return view('admin.shipping.create');
    }

    public function store(Request $request)
    {
        $method = ShippingMethod::create($this->validatedMethod($request));

        return redirect()->route('admin.shipping.edit', $method->id)->with('success', 'Layanan dibuat. Sekarang isi tarif per wilayah.');
    }

    public function edit(ShippingMethod $shippingMethod)
    {
        $rates = $shippingMethod->rates()->with(['province', 'city'])->get()
            ->sortBy(fn ($r) => $r->province->name . '|' . ($r->city_id ? '1' . $r->city->name : '0'))
            ->values();

        $coveredProvinces = $rates->whereNull('city_id')->pluck('province_id')->unique()->count();
        $totalProvinces = Province::count();

        return view('admin.shipping.edit', ['method' => $shippingMethod, 'rates' => $rates, 'coveredProvinces' => $coveredProvinces, 'totalProvinces' => $totalProvinces]);
    }

    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $shippingMethod->update($this->validatedMethod($request));

        return back()->with('success', 'Layanan diperbarui.');
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        // Soft delete: pesanan lama menyimpan nama layanan sebagai snapshot, jadi riwayat aman.
        $shippingMethod->delete();

        return redirect()->route('admin.shipping.index')->with('success', 'Layanan dihapus.');
    }

    /** Tambah / ubah satu tarif (kota kosong = seluruh provinsi). */
    public function storeRate(Request $request, ShippingMethod $shippingMethod)
    {
        $data = $this->validatedRate($request);

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $shippingMethod->id, 'province_id' => $data['province_id'], 'city_id' => $data['city_id'] ?? null],
            collect($data)->only(['rate_per_kg', 'min_charge', 'etd_min', 'etd_max'])->all()
        );

        return back()->with('success', 'Tarif disimpan.');
    }

    public function destroyRate(ShippingMethod $shippingMethod, ShippingRate $rate)
    {
        abort_unless($rate->shipping_method_id === $shippingMethod->id, 404);
        $rate->delete();

        return back()->with('success', 'Tarif dihapus.');
    }

    /** Isi tarif provinsi untuk semua provinsi sekaligus (lalu kecualikan kota tertentu satu per satu). */
    public function bulkRates(Request $request, ShippingMethod $shippingMethod)
    {
        $data = $request->validate([
            'rate_per_kg' => 'required|numeric|min:0|max:100000000',
            'min_charge'  => 'nullable|numeric|min:0|max:1000000000',
            'etd_min'     => 'nullable|integer|min:1|max:365',
            'etd_max'     => 'nullable|integer|min:1|max:365|gte:etd_min',
        ]);

        $overwrite = $request->boolean('overwrite');
        $created = 0;
        $updated = 0;

        foreach (Province::pluck('id') as $provinceId) {
            $existing = ShippingRate::where(['shipping_method_id' => $shippingMethod->id, 'province_id' => $provinceId])->whereNull('city_id')->first();

            if ($existing && !$overwrite) {
                continue;
            }

            $values = ['rate_per_kg' => $data['rate_per_kg'], 'min_charge' => $data['min_charge'] ?? 0, 'etd_min' => $data['etd_min'] ?? null, 'etd_max' => $data['etd_max'] ?? null];

            if ($existing) {
                $existing->update($values);
                $updated++;
            } else {
                ShippingRate::create($values + ['shipping_method_id' => $shippingMethod->id, 'province_id' => $provinceId, 'city_id' => null]);
                $created++;
            }
        }

        return back()->with('success', "Tarif provinsi: {$created} ditambahkan, {$updated} diperbarui.");
    }

    private function validatedMethod(Request $request): array
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function validatedRate(Request $request): array
    {
        return $request->validate([
            'province_id' => 'required|integer|exists:provinces,id',
            'city_id'     => ['nullable', 'integer', Rule::exists('cities', 'id')->where('province_id', $request->input('province_id'))],
            'rate_per_kg' => 'required|numeric|min:0|max:100000000',
            'min_charge'  => 'nullable|numeric|min:0|max:1000000000',
            'etd_min'     => 'nullable|integer|min:1|max:365',
            'etd_max'     => 'nullable|integer|min:1|max:365|gte:etd_min',
        ], ['city_id.exists' => 'Kota tidak sesuai dengan provinsi yang dipilih.']);
    }
}
