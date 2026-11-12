<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat 4 akun internal (Super Admin, Admin, Staff, Gudang) dengan password acak.
 *
 * - Password dibuat acak & hanya ditampilkan SEKALI di layar (tidak ada password default di kode).
 * - Aman dijalankan ulang: akun yang sudah ada tidak diubah, kecuali memakai --reset.
 * - Tidak menyentuh matriks hak akses yang sudah Anda atur di panel.
 */
class SetupStaffAccounts extends Command
{
    protected $signature = 'staff:setup
        {--domain=example.com : Domain email akun (admin@domain, staff@domain, dst)}
        {--super-email= : Email khusus untuk Super Admin (disarankan email asli Anda)}
        {--reset : Buat ulang password akun yang sudah ada}';

    protected $description = 'Buat akun Super Admin, Admin, Staff, dan Gudang dengan password acak.';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $domain = ltrim((string) $this->option('domain'), '@');
        $superEmail = $this->option('super-email') ?: "superadmin@{$domain}";

        $accounts = [
            ['role' => 'super_admin', 'name' => 'Super Admin',      'email' => $superEmail],
            ['role' => 'admin',       'name' => 'Admin Toko',       'email' => "admin@{$domain}"],
            ['role' => 'staff',       'name' => 'Staff Operasional', 'email' => "staff@{$domain}"],
            ['role' => 'gudang',      'name' => 'Admin Gudang',     'email' => "gudang@{$domain}"],
        ];

        foreach ($accounts as $a) {
            $validator = validator(['email' => $a['email']], ['email' => 'required|email']);
            if ($validator->fails()) {
                $this->error("Email tidak valid: {$a['email']}");

                return self::FAILURE;
            }
        }

        if (! \Spatie\Permission\Models\Permission::where('name', 'process_orders')->exists()) {
            $this->warn('Permission baru belum ada. Jalankan dulu: php artisan migrate --force');
        }

        $rows = [];
        foreach ($accounts as $a) {
            Role::firstOrCreate(['name' => $a['role'], 'guard_name' => 'web']);

            $user = User::withTrashed()->where('email', $a['email'])->first();

            if ($user && $user->hasRole('customer') && ! $user->hasAnyRole(['super_admin', 'admin', 'staff', 'gudang', 'keuangan'])) {
                $this->warn("Dilewati: {$a['email']} sudah dipakai akun pelanggan. Pakai email lain.");

                continue;
            }

            $plain = null;
            if (! $user) {
                $plain = Str::password(16, symbols: false);
                $user = User::create([
                    'name' => $a['name'],
                    'email' => $a['email'],
                    'password' => $plain, // di-hash otomatis oleh cast 'hashed'
                ]);
                $status = 'dibuat';
            } else {
                if ($user->trashed()) {
                    $user->restore();
                }
                if ($this->option('reset')) {
                    $plain = Str::password(16, symbols: false);
                    $user->password = $plain;
                    $status = 'password direset';
                } else {
                    $status = 'sudah ada (tidak diubah)';
                }
            }

            $user->email_verified_at ??= now();
            $user->save();
            $user->syncRoles([$a['role']]);

            $rows[] = [$a['role'], $a['email'], $plain ?? '-', $status];
        }

        $this->newLine();
        $this->table(['Role', 'Email', 'Password', 'Status'], $rows);
        $this->newLine();
        $this->warn('Simpan password di atas SEKARANG, tidak akan ditampilkan lagi.');
        $this->line('Setelah login: ganti password, dan ganti email jika memakai domain contoh (example.com).');

        return self::SUCCESS;
    }
}
