<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Memisahkan "mengisi kurir & resi" (manage_shipments: gudang, admin, staff) dari "menekan Kirim" (ship_orders: admin, staff).
 * Alur: gudang mengemas + mengisi resi -> admin mengecek lalu menekan Kirim.
 * Role kustom yang sudah punya manage_shipments otomatis mendapat ship_orders agar tidak tiba-tiba 403; gudang dikecualikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ship = Permission::findOrCreate('ship_orders', 'web');

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if ($role->name !== 'gudang' && $role->permissions->contains('name', 'manage_shipments')) {
                $role->givePermissionTo($ship);
            }
        }
        Role::where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($ship);
        Role::where('name', 'gudang')->where('guard_name', 'web')->first()?->revokePermissionTo($ship);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'ship_orders')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
