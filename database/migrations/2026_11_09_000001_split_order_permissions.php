<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Memecah hak "edit_orders" menjadi:
 *  - process_orders  : tandai pesanan "diproses" (kemas)  -> staff, admin, gudang
 *  - complete_orders : tandai pesanan "selesai"           -> staff, admin (BUKAN gudang)
 *
 * Selesai memulai batas retur 7 hari dan membuka testimoni, jadi tidak boleh ditutup oleh gudang.
 * Aman dijalankan ulang. Role kustom yang dulu punya edit_orders otomatis mendapat keduanya
 * supaya tidak ada yang tiba-tiba 403.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $process = Permission::findOrCreate('process_orders', 'web');
        $complete = Permission::findOrCreate('complete_orders', 'web');
        $finance = Permission::findOrCreate('view_finance', 'web');

        // Siapa pun yang dulu boleh edit_orders tetap boleh memproses & menyelesaikan.
        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if ($role->permissions->contains('name', 'edit_orders')) {
                $role->givePermissionTo($process, $complete);
            }
        }

        foreach (['super_admin', 'admin', 'staff'] as $name) {
            Role::where('name', $name)->where('guard_name', 'web')->first()?->givePermissionTo($process, $complete, $finance);
        }

        // Gudang: boleh kemas & kirim, tidak boleh menutup pesanan dan tidak melihat nominal uang.
        $gudang = Role::where('name', 'gudang')->where('guard_name', 'web')->first();
        if ($gudang) {
            $gudang->revokePermissionTo(['edit_orders', 'complete_orders', 'view_finance']);
            $gudang->givePermissionTo($process);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $gudang = Role::where('name', 'gudang')->where('guard_name', 'web')->first();
        $gudang?->givePermissionTo('edit_orders');

        Permission::whereIn('name', ['process_orders', 'complete_orders'])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
