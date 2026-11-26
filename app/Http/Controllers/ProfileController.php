<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesImageUploads;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Profil akun panel admin/staf (Breeze).
 * Profil pelanggan ada di App\Http\Controllers\Web\ProfileController.
 */
class ProfileController extends Controller
{
    use HandlesImageUploads;

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Foto profil & sampul opsional. URL foto Google tidak dihapus dari disk.
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

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

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

    private function deleteOwnAvatar(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            $this->deleteImage($path);
        }
    }
}
