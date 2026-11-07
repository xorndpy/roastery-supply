<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Izin modul Artikel dan Milestone untuk database yang sudah berjalan (seeder tidak perlu diulang). */
return new class extends Migration
{
    private function names(): array
    {
        $out = [];
        foreach (['articles', 'milestones'] as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $out[] = "{$action}_{$module}";
            }
        }

        return $out;
    }

    public function up(): void
    {
        $names = $this->names();
        foreach ($names as $n) {
            Permission::findOrCreate($n, 'web');
        }
        foreach (['admin', 'super_admin'] as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($names);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->names())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
