<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Super Admin: All permissions
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->syncPermissions(Permission::all());
        }

        // 2. Admin: All permissions EXCEPT user/role/audit log management (Super Admin exclusive per PRD 6.7)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminPermissions = Permission::whereNotIn('name', [
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'view_roles',
                'manage_roles',
                'view_activity_logs',
            ])->get();
            $adminRole->syncPermissions($adminPermissions);
        }

        // 3. Staff: Catalog & Stock view/manage, Orders/Payments/Shipments/Returns operational
        $staffRole = Role::where('name', 'staff')->first();
        if ($staffRole) {
            $staffPermissions = [
                'view_products',
                'view_categories',
                'view_brands',
                'view_stock',
                'manage_stock',
                'view_orders',
                'edit_orders',
                'verify_payments',
                'reject_payments',
                'manage_shipments',
                'view_returns',
                'download_invoices',
                'view_customers',
                'view_contact_messages',
                'reply_contact_messages',
            ];
            $staffRole->syncPermissions($staffPermissions);
        }

        // 4. Customer: Customer portal access
        $customerRole = Role::where('name', 'customer')->first();
        if ($customerRole) {
            $customerPermissions = [
                'view_own_orders',
                'create_orders',
                'request_returns',
                'view_own_profile',
                'edit_own_profile',
            ];
            $customerRole->syncPermissions($customerPermissions);
        }

        // 5. Guest: Public browsing
        $guestRole = Role::where('name', 'guest')->first();
        if ($guestRole) {
            $guestRole->syncPermissions([]);
        }
    }
}
