<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Buku alamat pelanggan: beberapa alamat kirim, satu di antaranya utama. */
class AddressController extends Controller
{
    private function customer(): Customer
    {
        $user = auth()->user();

        return $user->customer ?? Customer::create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    }

    private function owned(CustomerAddress $address): CustomerAddress
    {
        abort_unless($address->customer_id === $this->customer()->id, 404);

        return $address;
    }

    public function index()
    {
        $customer = $this->customer();
        $addresses = $customer->addresses()->with(['city', 'province'])->orderByDesc('is_default')->orderBy('id')->get();

        return view('customer.addresses.index', ['addresses' => $addresses, 'limit' => CustomerAddress::LIMIT]);
    }

    public function create()
    {
        $customer = $this->customer();
        if ($customer->addresses()->count() >= CustomerAddress::LIMIT) {
            return redirect()->route('customer.addresses.index')->with('error', 'Maksimal ' . CustomerAddress::LIMIT . ' alamat. Hapus salah satu dulu.');
        }

        return view('customer.addresses.form', ['address' => new CustomerAddress(['recipient_name' => auth()->user()->name, 'phone' => auth()->user()->phone]), 'isFirst' => ! $customer->addresses()->exists()]);
    }

    public function store(Request $request)
    {
        $customer = $this->customer();
        if ($customer->addresses()->count() >= CustomerAddress::LIMIT) {
            return redirect()->route('customer.addresses.index')->with('error', 'Maksimal ' . CustomerAddress::LIMIT . ' alamat.');
        }
        $data = $this->validated($request);

        DB::transaction(function () use ($customer, $data, $request) {
            $makeDefault = $request->boolean('is_default') || ! $customer->addresses()->exists();
            $address = $customer->addresses()->create($data + ['is_default' => false]);
            if ($makeDefault) {
                $this->setDefault($customer, $address);
            }
        });

        return redirect()->route('customer.addresses.index')->with('success', 'Alamat ditambahkan.');
    }

    public function edit(CustomerAddress $address)
    {
        return view('customer.addresses.form', ['address' => $this->owned($address), 'isFirst' => false]);
    }

    public function update(Request $request, CustomerAddress $address)
    {
        $address = $this->owned($address);
        $data = $this->validated($request);

        DB::transaction(function () use ($address, $data, $request) {
            $address->update($data);
            if ($request->boolean('is_default') || $address->is_default) {
                $this->setDefault($this->customer(), $address->refresh());
            }
        });

        return redirect()->route('customer.addresses.index')->with('success', 'Alamat diperbarui.');
    }

    public function makeDefault(CustomerAddress $address)
    {
        $address = $this->owned($address);
        DB::transaction(fn () => $this->setDefault($this->customer(), $address));

        return back()->with('success', 'Alamat utama diganti.');
    }

    public function destroy(CustomerAddress $address)
    {
        $address = $this->owned($address);
        $customer = $this->customer();

        DB::transaction(function () use ($address, $customer) {
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault && ($next = $customer->addresses()->orderBy('id')->first())) {
                $this->setDefault($customer, $next);
            }
        });

        return back()->with('success', 'Alamat dihapus.');
    }

    /** Jadikan utama; data profil pelanggan ikut disamakan supaya checkout dan ongkir memakai alamat ini. */
    private function setDefault(Customer $customer, CustomerAddress $address): void
    {
        $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
        $customer->update([
            'phone' => $address->phone,
            'address' => $address->address,
            'province_id' => $address->province_id,
            'city_id' => $address->city_id,
            'postal_code' => $address->postal_code,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => 'required|string|max:40',
            'recipient_name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'province_id' => 'required|integer|exists:provinces,id',
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('province_id', $request->input('province_id'))],
            'postal_code' => 'required|digits:5',
        ], [
            'label.required' => 'Beri nama alamat, misalnya "Kedai Utama".',
            'city_id.exists' => 'Kota tidak sesuai dengan provinsi yang dipilih.',
            'postal_code.digits' => 'Kode pos harus 5 digit angka.',
        ]);
    }
}
