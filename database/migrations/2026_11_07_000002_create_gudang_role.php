<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Role Admin Gudang untuk database yang sudah berjalan (seeder tidak perlu diulang). */
return new class extends Migration
{
    private function permissions(): array
    {
        return [
            'view_products', 'view_categories', 'view_brands',
            'view_stock', 'manage_stock',
            'view_orders', 'edit_orders', 'manage_shipments',
            'view_returns',
        ];
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'gudang', 'guard_name' => 'web']);
        $role->syncPermissions(
            Permission::where('guard_name', 'web')->whereIn('name', $this->permissions())->pluck('name')->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'gudang')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
