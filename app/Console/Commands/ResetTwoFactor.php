<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Pintu darurat bila admin kehilangan HP + kode pemulihan: jalankan lewat terminal server (SSH / Terminal cPanel). */
class ResetTwoFactor extends Command
{
    protected $signature = '2fa:reset {email : Email akun yang 2FA-nya dihapus}';

    protected $description = 'Hapus 2FA sebuah akun (akun itu wajib memasang ulang saat login berikutnya).';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->info("2FA untuk {$user->email} dihapus.");

        return self::SUCCESS;
    }
}
