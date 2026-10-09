<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('customer.profile');
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'province_id' => 'nullable|integer|exists:provinces,id',
            'city_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('cities', 'id')->where('province_id', $request->input('province_id'))],
            'postal_code' => 'nullable|digits:5',
        ], ['city_id.exists' => 'Kota tidak sesuai dengan provinsi yang dipilih.', 'postal_code.digits' => 'Kode pos harus 5 digit angka.']);

        $province = isset($validated['province_id']) ? \App\Models\Province::find($validated['province_id']) : null;
        $city = isset($validated['city_id']) ? \App\Models\City::find($validated['city_id']) : null;

        // Update user
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        // Update/bikin customer
        Customer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'province_id' => $province?->id,
                'city_id' => $city?->id,
                'city' => $city?->name,
                'province' => $province?->name,
                'postal_code' => $validated['postal_code'] ?? null,
            ]
        );

        return back()->with('success', 'Profil berhasil diupdate.');
    }
}