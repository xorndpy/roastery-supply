<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizePaymentProofs extends Command
{
    protected $signature = 'payments:privatize';

    protected $description = 'Pindahkan bukti bayar lama dari disk publik ke disk privat.';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;
        $missing = 0;

        Payment::whereNotNull('proof_image')->each(function (Payment $p) use ($public, $private, &$moved, &$missing) {
            if ($private->exists($p->proof_image)) {
                $public->delete($p->proof_image); // sudah ada di privat; bersihkan sisa publik

                return;
            }
            if (!$public->exists($p->proof_image)) {
                $missing++;

                return;
            }

            $private->put($p->proof_image, $public->get($p->proof_image));
            $public->delete($p->proof_image);
            $moved++;
        });

        $this->info("{$moved} file dipindahkan ke penyimpanan privat." . ($missing ? " {$missing} file tidak ditemukan." : ''));

        return self::SUCCESS;
    }
}
