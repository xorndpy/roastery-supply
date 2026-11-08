<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // PRODUKSI: hanya satu Super Admin, kredensial dari .env (tanpa akun demo / password default).
        if (app()->environment('production')) {
            $email = env('ADMIN_EMAIL');
            $password = env('ADMIN_PASSWORD');

            if (! $email || ! $password) {
                $this->command?->warn('Isi ADMIN_EMAIL dan ADMIN_PASSWORD di .env, lalu jalankan ulang: php artisan db:seed --class=UserSeeder --force');

                return;
            }

            $user = User::updateOrCreate(['email' => $email], [
                'name' => env('ADMIN_NAME', 'Super Administrator'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $user->syncRoles(['super_admin']);

            return;
        }

        $this->demoAccounts();
    }

    /** Akun demo, hanya untuk lokal / testing. */
    private function demoAccounts(): void
    {
        $make = fn (string $email, string $name, string $phone, string $role) => tap(User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'phone' => $phone, 'password' => Hash::make('password'), 'email_verified_at' => now()]
        ), fn ($u) => $u->syncRoles([$role]));

        $make('super@roastery.com', 'Super Administrator', '08110000001', 'super_admin');
        $make('admin@roastery.com', 'Store Administrator', '08110000002', 'admin');
        $make('staff@roastery.com', 'Operations Staff', '08110000003', 'staff');
        $make('gudang@roastery.com', 'Admin Gudang', '08110000004', 'gudang');
        $customerUser = $make('customer@roastery.com', 'Budi Santoso', '081234567890', 'customer');

        Customer::updateOrCreate(['user_id' => $customerUser->id], [
            'name' => $customerUser->name,
            'email' => $customerUser->email,
            'phone' => $customerUser->phone,
            'address' => 'Jl. Kopi Specialty No. 10, Kebayoran',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12190',
        ]);
    }
}
