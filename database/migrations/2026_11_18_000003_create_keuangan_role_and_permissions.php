<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Role Admin Keuangan + permission modul keuangan, untuk database yang sudah berjalan (seeder tidak perlu diulang). */
return new class extends Migration
{
    private function newPermissions(): array
    {
        return [
            'view_profit', 'view_costs', 'manage_costs',
            'view_expenses', 'create_expenses', 'edit_expenses', 'delete_expenses',
        ];
    }

    private function keuanganPermissions(): array
    {
        return array_merge($this->newPermissions(), [
            'view_finance', 'view_orders', 'download_invoices', 'view_returns', 'view_reports', 'export_reports',
        ]);
    }

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->newPermissions() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'keuangan', 'guard_name' => 'web']);
        $role->syncPermissions(
            Permission::where('guard_name', 'web')->whereIn('name', $this->keuanganPermissions())->pluck('name')->all()
        );

        foreach (['super_admin', 'admin'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($this->newPermissions());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'keuangan')->where('guard_name', 'web')->delete();
        Permission::where('guard_name', 'web')->whereIn('name', $this->newPermissions())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
