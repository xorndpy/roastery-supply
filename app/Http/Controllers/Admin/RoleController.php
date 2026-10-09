<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Matriks Role & Hak Akses (PRD 6.7). Perubahan di sini langsung berlaku,
 * karena semua route admin dijaga dengan middleware can:<permission>.
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])->orderBy('id')->get();
        $locked = config('rbac.locked_roles');

        return view('admin.roles.index', compact('roles', 'locked'));
    }

    public function edit(Role $role)
    {
        $locked = in_array($role->name, config('rbac.locked_roles'), true);
        $groups = $this->groupedPermissions();
        $granted = $role->permissions->pluck('name')->all();

        return view('admin.roles.edit', compact('role', 'groups', 'granted', 'locked'));
    }

    public function update(Request $request, Role $role)
    {
        if (in_array($role->name, config('rbac.locked_roles'), true)) {
            return back()->with('error', "Role {$role->name} dikunci dan tidak bisa diubah.");
        }

        $validated = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $new = collect($validated['permissions'] ?? []);

        // Jangan sampai role admin kehilangan akses mengelola dirinya sendiri ke area yang bukan haknya:
        // izin administrasi (user/role/log) khusus Super Admin per PRD.
        $new = $new->reject(fn ($p) => in_array($p, [
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_roles', 'manage_roles', 'view_activity_logs',
        ], true));

        $old = $role->permissions->pluck('name');
        $role->syncPermissions($new->all());

        $added = $new->diff($old)->values()->all();
        $removed = $old->diff($new)->values()->all();

        if ($added || $removed) {
            activity('role')->performedOn($role)
                ->withProperties(['added' => $added, 'removed' => $removed])
                ->log("Hak akses role {$role->name} diubah (+" . count($added) . ' / -' . count($removed) . ')');
        }

        return redirect()->route('admin.roles.index')->with('success', "Hak akses role {$role->name} disimpan.");
    }

    /**
     * @return array<string, array<int, array{name:string,label:string}>>
     */
    private function groupedPermissions(): array
    {
        $all = Permission::orderBy('name')->pluck('name')->all();
        $verbs = config('rbac.verbs');
        $result = [];
        $placed = [];

        foreach (config('rbac.groups') as $group => $names) {
            foreach ($names as $name) {
                if (in_array($name, $all, true)) {
                    $result[$group][] = ['name' => $name, 'label' => $this->label($name, $verbs)];
                    $placed[] = $name;
                }
            }
        }

        foreach (array_diff($all, $placed) as $name) {
            $result['Lainnya'][] = ['name' => $name, 'label' => $this->label($name, $verbs)];
        }

        return $result;
    }

    private function label(string $name, array $verbs): string
    {
        foreach ($verbs as $verb => $text) {
            if (str_starts_with($name, $verb . '_')) {
                return $text . ' ' . str_replace('_', ' ', substr($name, strlen($verb) + 1));
            }
        }

        return str_replace('_', ' ', $name);
    }
}
