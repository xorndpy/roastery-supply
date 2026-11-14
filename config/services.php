<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'rajaongkir' => [
        'key'      => env('RAJAONGKIR_API_KEY'),
        'origin'   => env('RAJAONGKIR_ORIGIN_ID'),
        'couriers' => env('RAJAONGKIR_COURIERS', 'jne:sicepat:jnt'),
        'track_url' => env('RAJAONGKIR_TRACK_URL', 'https://rajaongkir.komerce.id/api/v1/track/waybill'),
    ],

    // WhatsApp via Fonnte (https://docs.fonnte.com). Token HANYA di .env; kosong = WhatsApp nonaktif.
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'url' => env('WHATSAPP_API_URL', 'https://api.fonnte.com/send'),
    ],

    // Cloudflare Web Analytics (token bersifat publik, aman). Kosongkan untuk menonaktifkan.
    'cloudflare' => [
        'beacon_token' => env('CF_BEACON_TOKEN', '6f32181eec184964bfb263a78065f05d'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Kosongkan agar otomatis memakai route('auth.google.callback').
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Midtrans (payment gateway)
    |--------------------------------------------------------------------------
    | Sandbox untuk pengembangan/demo (kunci dari dashboard sandbox Midtrans).
    | Produksi: isi kunci produksi dan set MIDTRANS_IS_PRODUCTION=true.
    | verify_status_api: konfirmasi status ke API Midtrans sebelum mengubah pesanan.
    |   null = otomatis (aktif hanya di produksi, supaya perintah payments:simulate tetap bisa dipakai di lokal).
    */
    'midtrans' => [
        'server_key'        => env('MIDTRANS_SERVER_KEY'),
        'client_key'        => env('MIDTRANS_CLIENT_KEY'),
        'is_production'     => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'verify_status_api' => env('MIDTRANS_VERIFY_STATUS_API'),
        'expiry_minutes'    => (int) env('MIDTRANS_EXPIRY_MINUTES', 1440),
        // true = notifikasi diproses langsung di dalam request (tanpa worker). false = lewat antrean (butuh queue:work).
        'process_inline'    => (bool) env('MIDTRANS_PROCESS_INLINE', true),
    ],

];
