<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\MidtransService;
use App\Services\Payments\PaymentReconciler;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--payment= : ID pembayaran tertentu (diproses langsung)} {--force : abaikan jeda pengecekan}';

    protected $description = 'Cek status pembayaran gateway yang masih menunggu langsung ke Midtrans (jaring pengaman webhook).';

    public function handle(PaymentReconciler $reconciler, MidtransService $midtrans): int
    {
        if (!$midtrans->configured()) {
            $this->warn('Midtrans belum dikonfigurasi (MIDTRANS_SERVER_KEY / MIDTRANS_CLIENT_KEY). Dilewati.');

            return self::SUCCESS;
        }

        if ($id = $this->option('payment')) {
            $payment = Payment::findOrFail($id);
            $this->info("Pembayaran #{$payment->id}: " . $reconciler->check($payment, sync: true));

            return self::SUCCESS;
        }

        $s = $reconciler->sweep((bool) $this->option('force'));
        $this->info("Dicek: {$s['dicek']}, dijadwalkan: {$s['dijadwalkan']}, kedaluwarsa: {$s['kedaluwarsa']}, event tersangkut dikirim ulang: {$s['tersangkut']}.");

        return self::SUCCESS;
    }
}
