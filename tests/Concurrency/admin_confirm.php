<?php
// Satu "klik admin" memverifikasi pembayaran manual (jalur OrderService::confirmPayment) di proses terpisah.
$root = dirname(__DIR__, 2);
chdir($root);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[, $orderId, $paymentId, $actor] = $argv;
try {
    app(App\Services\OrderService::class)->confirmPayment(App\Models\Order::findOrFail($orderId), (int) $actor, App\Models\Payment::findOrFail($paymentId));
    echo "OK";
} catch (Throwable $e) { echo "DITOLAK: " . $e->getMessage(); }
