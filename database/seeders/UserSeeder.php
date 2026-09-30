<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'super@roastery.com'],
            [
                'name' => 'Super Administrator',
                'phone' => '08110000001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // 2. Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@roastery.com'],
            [
                'name' => 'Store Administrator',
                'phone' => '08110000002',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        // 3. Staff
        $staff = User::updateOrCreate(
            ['email' => 'staff@roastery.com'],
            [
                'name' => 'Operations Staff',
                'phone' => '08110000003',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $staff->syncRoles(['staff']);

        // 4. Customer
        $customerUser = User::updateOrCreate(
            ['email' => 'customer@roastery.com'],
            [
                'name' => 'Budi Santoso',
                'phone' => '081234567890',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $customerUser->syncRoles(['customer']);

        // Customer Profile
        Customer::updateOrCreate(
            ['user_id' => $customerUser->id],
            [
                'name' => $customerUser->name,
                'email' => $customerUser->email,
                'phone' => $customerUser->phone,
                'address' => 'Jl. Kopi Specialty No. 10, Kebayoran',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12190',
            ]
        );
    }
}
