<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Kirim tautan atur ulang password ke email akun.
     * Jawaban sama untuk email terdaftar maupun tidak, supaya tidak membocorkan siapa yang punya akun.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['email' => ['required', 'email'], 'cf-turnstile-response' => [new \App\Rules\Turnstile]],
            ['email.required' => 'Email wajib diisi.', 'email.email' => 'Format email tidak valid. Contoh: nama@gmail.com'],
        );

        try {
            $status = Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            // Pengiriman email gagal (SMTP salah/down): catat di log, beri pesan yang jelas ke pengguna.
            report($e);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Email belum bisa dikirim saat ini. Coba lagi beberapa saat atau hubungi admin.']);
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Permintaan terlalu sering. Tunggu satu menit lalu coba lagi.']);
        }

        return back()->with('status', 'Jika email itu terdaftar, tautan atur ulang password sudah dikirim. Periksa kotak masuk dan folder spam.');
    }
}
