<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Payments\MidtransService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Alat demo/pengujian: mengirim notifikasi Midtrans BERTANDA TANGAN ASLI ke endpoint webhook kita sendiri,
 * melewati seluruh jalur yang sama dengan notifikasi sungguhan (verifikasi, pencatatan event, antrean, pemrosesan).
 * Dengan --times=3 kita bisa memperagakan "notifikasi datang berkali-kali" tanpa menunggu Midtrans.
 * Dinonaktifkan di produksi.
 */
class SimulatePayment extends Command
{
    protected $signature = 'payments:simulate
        {order_number : Nomor pesanan}
        {status=settlement : settlement | pending | expire | deny | cancel | capture | refund}
        {--times=1 : Kirim berapa kali (uji notifikasi ganda)}
        {--amount= : Nominal yang dikirim (default = tagihan)}
        {--fraud=accept : accept | challenge | deny (untuk capture)}
        {--type=bank_transfer : payment_type}
        {--create : Buat percobaan pembayaran lokal bila belum ada (tanpa memanggil Midtrans)}
        {--bad-signature : Kirim dengan tanda tangan salah}';

    protected $description = 'Simulasikan notifikasi pembayaran Midtrans ke endpoint webhook (hanya non-produksi).';

    public function handle(MidtransService $midtrans): int
    {
        if ($midtrans->isProduction()) {
            $this->error('Perintah ini dinonaktifkan di mode produksi.');

            return self::FAILURE;
        }
        if (!filled(config('services.midtrans.server_key'))) {
            $this->error('MIDTRANS_SERVER_KEY belum diisi di .env (kunci sandbox sudah cukup).');

            return self::FAILURE;
        }

        $order = Order::where('order_number', $this->argument('order_number'))->first();
        if (!$order) {
            $this->error('Pesanan tidak ditemukan.');

            return self::FAILURE;
        }

        $payment = $order->payments()->where('provider', 'midtrans')->latest('id')->first();
        if (!$payment || ($this->option('create') && $payment->status !== 'waiting')) {
            if (!$this->option('create')) {
                $this->error('Belum ada percobaan pembayaran online. Klik "Bayar Online" di halaman pesanan, atau tambahkan --create.');

                return self::FAILURE;
            }
            $seq = $order->payments()->where('provider', 'midtrans')->count() + 1;
            $payment = $order->payments()->create([
                'method' => 'midtrans', 'provider' => 'midtrans',
                'provider_order_id' => $order->order_number . '-P' . $seq,
                'amount' => $order->total, 'status' => 'waiting',
                'expires_at' => now()->addHours(OrderService::PAYMENT_WINDOW_HOURS),
            ]);
            $this->line("Percobaan pembayaran dibuat: {$payment->provider_order_id}");
        }

        $status = (string) $this->argument('status');
        $gross = number_format((float) ($this->option('amount') ?: $payment->amount), 2, '.', '');
        $code = match ($status) {
            'pending' => '201',
            'settlement', 'capture' => '200',
            default => '202',
        };

        $payload = [
            'transaction_time'   => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'transaction_status' => $status,
            'transaction_id'     => 'sim-' . $payment->id,
            'status_message'     => 'midtrans payment notification (simulasi)',
            'status_code'        => $code,
            'payment_type'       => $this->option('type'),
            'order_id'           => $payment->provider_order_id,
            'merchant_id'        => 'SIMULASI',
            'gross_amount'       => $gross,
            'fraud_status'       => $this->option('fraud'),
            'currency'           => 'IDR',
        ];
        if (in_array($status, ['settlement', 'capture'], true)) {
            $payload['settlement_time'] = $payload['transaction_time'];
        }
        $payload['signature_key'] = $this->option('bad-signature')
            ? str_repeat('0', 128)
            : $midtrans->signatureFor($payload['order_id'], $code, $gross);

        $kernel = app(Kernel::class);
        $times = max(1, (int) $this->option('times'));

        $this->line("Mengirim '{$status}' sebanyak {$times}× untuk {$payload['order_id']} (Rp " . number_format((float) $gross, 0, ',', '.') . ')');
        for ($i = 1; $i <= $times; $i++) {
            $request = Request::create('/webhooks/midtrans', 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ], json_encode($payload));
            $request->attributes->set('payment_source', 'simulasi');

            $response = $kernel->handle($request);
            $this->line("  #{$i} → HTTP {$response->getStatusCode()} " . $response->getContent());
        }

        if (config('queue.default') !== 'sync') {
            $this->comment('QUEUE_CONNECTION=' . config('queue.default') . ': jalankan "php artisan queue:work --stop-when-empty" agar antrean diproses.');
        }

        $payment->refresh();
        $order->refresh();
        $this->info("Hasil → pesanan: {$order->status} | pembayaran: {$payment->status}" . ($payment->review_reason ? " ({$payment->review_reason})" : ''));

        return self::SUCCESS;
    }
}
