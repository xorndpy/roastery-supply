<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionsByModule = [
            'katalog' => [
                'view_products',
                'create_products',
                'edit_products',
                'delete_products',
                'view_categories',
                'create_categories',
                'edit_categories',
                'delete_categories',
                'view_brands',
                'create_brands',
                'edit_brands',
                'delete_brands',
                'view_stock',
                'manage_stock',
                'import_export_products',
            ],
            'penjualan' => [
                'view_orders',
                'edit_orders',
                'cancel_orders',
                'verify_payments',
                'reject_payments',
                'manage_shipments',
                'view_returns',
                'approve_returns',
                'reject_returns',
                'download_invoices',
            ],
            'pelanggan' => [
                'view_customers',
                'edit_customers',
                'export_customers',
            ],
            'pemasaran' => [
                'view_vouchers',
                'create_vouchers',
                'edit_vouchers',
                'delete_vouchers',
                'view_banners',
                'create_banners',
                'edit_banners',
                'delete_banners',
                'view_testimonials',
                'approve_testimonials',
                'delete_testimonials',
            ],
            'cms' => [
                'view_faqs',
                'create_faqs',
                'edit_faqs',
                'delete_faqs',
                'view_partners',
                'create_partners',
                'edit_partners',
                'delete_partners',
                'view_branches',
                'create_branches',
                'edit_branches',
                'delete_branches',
                'view_settings',
                'edit_settings',
                'view_contact_messages',
                'reply_contact_messages',
                'delete_contact_messages',
            ],
            'laporan' => [
                'view_reports',
                'export_reports',
            ],
            'administrasi' => [
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'view_roles',
                'manage_roles',
                'view_activity_logs',
            ],
            'customer_portal' => [
                'view_own_orders',
                'create_orders',
                'request_returns',
                'view_own_profile',
                'edit_own_profile',
            ],
        ];

        foreach ($permissionsByModule as $module => $permissions) {
            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }
        }
    }
}
