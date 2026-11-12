<?php

use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    
        then: function () {
            // Sengaja di luar grup 'web': webhook dari Midtrans tidak membawa token CSRF maupun sesi.
            // Keaslian dijamin tanda tangan SHA512 di MidtransWebhookController.
            Route::middleware('throttle:600,1')
                ->post('/webhooks/midtrans', \App\Http\Controllers\Webhooks\MidtransWebhookController::class)
                ->name('webhooks.midtrans');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Di belakang proxy/CDN (Cloudflare, LiteSpeed, dll): percaya X-Forwarded-* agar IP klien & skema HTTPS terbaca benar.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            '2fa' => RequireTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();