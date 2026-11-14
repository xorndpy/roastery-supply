<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $perms = ['view_service', 'manage_service', 'view_warranties', 'manage_warranties'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        foreach (['super_admin', 'admin', 'staff'] as $name) {
            Role::where('name', $name)->where('guard_name', 'web')->first()?->givePermissionTo($this->perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->whereIn('name', $this->perms)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
