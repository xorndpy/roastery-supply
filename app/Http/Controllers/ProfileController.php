<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use HandlesImageUploads;

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        // Halaman ini memakai tampilan panel admin; pelanggan punya halaman profil sendiri.
        if (! $request->user()->hasAnyRole(['super_admin', 'admin', 'staff', 'gudang'])) {
            return Redirect::route('customer.profile.edit');
        }

        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Foto profil: unggah baru (ganti yang lama) atau hapus.
        if ($request->hasFile('avatar')) {
            $this->deleteOwnAvatar($user->avatar);
            $user->avatar = $this->storeImage($request->file('avatar'), 'avatars');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteOwnAvatar($user->avatar);
            $user->avatar = null;
        }

        // Foto sampul: unggah baru (ganti yang lama) atau hapus (kembali ke pola bawaan).
        if ($request->hasFile('cover')) {
            $this->deleteImage($user->cover);
            $user->cover = $this->storeImage($request->file('cover'), 'covers');
        } elseif ($request->boolean('remove_cover')) {
            $this->deleteImage($user->cover);
            $user->cover = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /** Hanya file unggahan yang dihapus dari disk; URL foto Google dibiarkan. */
    private function deleteOwnAvatar(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            $this->deleteImage($path);
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
