<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use HandlesImageUploads;

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
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'city_id.exists' => 'Kota tidak sesuai dengan provinsi yang dipilih.',
            'postal_code.digits' => 'Kode pos harus 5 digit angka.',
            'avatar.image' => 'Foto profil harus berupa gambar.',
            'avatar.mimes' => 'Foto profil harus JPG, PNG, atau WebP.',
            'avatar.max' => 'Ukuran foto profil maksimal 2 MB.',
            'cover.image' => 'Foto sampul harus berupa gambar.',
            'cover.mimes' => 'Foto sampul harus JPG, PNG, atau WebP.',
            'cover.max' => 'Ukuran foto sampul maksimal 4 MB.',
        ]);

        $province = isset($validated['province_id']) ? \App\Models\Province::find($validated['province_id']) : null;
        $city = isset($validated['city_id']) ? \App\Models\City::find($validated['city_id']) : null;

        // Update user
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        // Foto profil & sampul bersifat opsional. Foto lama dihapus saat diganti; URL foto Google tidak dihapus dari disk.
        if ($request->hasFile('avatar')) {
            $this->deleteOwnAvatar($user->avatar);
            $user->avatar = $this->storeImage($request->file('avatar'), 'avatars');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteOwnAvatar($user->avatar);
            $user->avatar = null;
        }

        if ($request->hasFile('cover')) {
            $this->deleteImage($user->cover);
            $user->cover = $this->storeImage($request->file('cover'), 'covers');
        } elseif ($request->boolean('remove_cover')) {
            $this->deleteImage($user->cover);
            $user->cover = null;
        }

        $user->save();

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

    /** Hanya file unggahan yang dihapus dari disk; URL foto Google dibiarkan. */
    private function deleteOwnAvatar(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            $this->deleteImage($path);
        }
    }
}
