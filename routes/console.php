<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// PRD aturan #6: auto-cancel order yang tidak dibayar dalam 24 jam.
// Butuh cron: * * * * * php /path/to/artisan schedule:run
Schedule::command('orders:cancel-expired')->hourly()->withoutOverlapping();

// Jaring pengaman pembayaran gateway: webhook bisa hilang, dan Midtrans tidak mengirim webhook saat sesi Snap kedaluwarsa.
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Lacak resi otomatis; pesanan yang sudah diterima kurir otomatis jadi selesai.
Schedule::command('tracking:sync')->everyThirtyMinutes()->withoutOverlapping();
