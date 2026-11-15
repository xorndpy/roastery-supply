<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $perms = ['view_flash_sales', 'create_flash_sales', 'edit_flash_sales', 'delete_flash_sales'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        foreach (['super_admin', 'admin'] as $name) {
            Role::where('name', $name)->where('guard_name', 'web')->first()?->givePermissionTo($this->perms);
        }
        Role::where('name', 'staff')->where('guard_name', 'web')->first()?->givePermissionTo('view_flash_sales');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->whereIn('name', $this->perms)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
