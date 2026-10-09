<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Manajemen user & role (hanya Super Admin, PRD 6.7).
 * Pengaman: tidak boleh menghapus/menurunkan diri sendiri, dan harus selalu tersisa 1 Super Admin.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $users = $query->latest('id')->paginate(15)->withQueryString();
        $roles = Role::orderBy('name')->pluck('name');

        $stats = [
            'total' => User::count(),
            'staff' => User::role(['super_admin', 'admin', 'staff'])->count(),
            'customer' => User::role('customer')->count(),
        ];

        return view('admin.users.index', compact('users', 'roles', 'stats'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(), 'roles' => $this->assignableRoles(), 'currentRole' => null]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone'    => 'nullable|string|max:20',
            'role'     => ['required', Rule::in($this->assignableRoles()->pluck('name'))],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'], // di-hash oleh cast model
        ]);
        $user->email_verified_at = now(); // dibuat oleh admin -> dianggap terverifikasi
        $user->save();
        $user->syncRoles([$validated['role']]);

        activity('user')->performedOn($user)->withProperties(['role' => $validated['role']])->log('User dibuat dengan role ' . $validated['role']);

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} dibuat.");
    }

    public function show(User $user)
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'currentRole' => $user->roles->first()?->name,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => 'nullable|string|max:20',
            'role'     => ['required', Rule::in($this->assignableRoles()->pluck('name'))],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $oldRole = $user->roles->first()?->name;

        if ($oldRole !== $validated['role']) {
            if ($user->id === auth()->id()) {
                return back()->withInput()->with('error', 'Kamu tidak bisa mengubah role akunmu sendiri.');
            }
            if ($oldRole === 'super_admin' && $this->isLastSuperAdmin($user)) {
                return back()->withInput()->with('error', 'Harus tersisa minimal satu Super Admin.');
            }
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);
        if (!empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        if ($oldRole !== $validated['role']) {
            $user->syncRoles([$validated['role']]);
            activity('user')->performedOn($user)
                ->withProperties(['old' => ['role' => $oldRole], 'attributes' => ['role' => $validated['role']]])
                ->log("Role diubah: {$oldRole} → {$validated['role']}");
        }
        if (!empty($validated['password'])) {
            activity('user')->performedOn($user)->log('Password direset oleh admin');
        }

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} diperbarui.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        }
        if ($user->hasRole('super_admin') && $this->isLastSuperAdmin($user)) {
            return back()->with('error', 'Harus tersisa minimal satu Super Admin.');
        }

        // Soft delete: riwayat order dan log tetap utuh.
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} dihapus.");
    }

    private function assignableRoles()
    {
        return Role::whereIn('name', ['super_admin', 'admin', 'staff', 'customer'])->orderBy('id')->get(['id', 'name']);
    }

    private function isLastSuperAdmin(User $user): bool
    {
        return User::role('super_admin')->where('id', '!=', $user->id)->doesntExist();
    }
}
