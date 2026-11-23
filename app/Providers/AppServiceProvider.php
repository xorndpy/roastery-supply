<?php

namespace App\Providers;

use App\Models\Setting;
use App\View\Composers\ShopNavComposer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Email atur ulang password berbahasa Indonesia, dikirim ke email akun yang bersangkutan.
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $minutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
            $site = Setting::get('site_name', config('app.name'));

            return (new MailMessage)
                ->subject("Atur ulang password {$site}")
                ->greeting('Halo ' . ($notifiable->name ?: '') . ',')
                ->line('Kami menerima permintaan untuk mengatur ulang password akun Anda.')
                ->action('Atur ulang password', $url)
                ->line("Tautan ini berlaku {$minutes} menit dan hanya bisa dipakai sekali.")
                ->line('Jika Anda tidak merasa memintanya, abaikan email ini. Password Anda tidak berubah.')
                ->salutation("Salam, {$site}");
        });

        // Paksa HTTPS di production (buat fix Mixed Content)
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Super Admin selalu lolos semua izin (PRD 6.7), termasuk izin yang baru ditambahkan nanti.
        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);

        // Pengaturan situs (nama, kontak, sosmed, rekening, meta) tersedia di semua halaman publik sebagai $site.
        View::composer(['layouts.*', 'components.*', 'customer.*', 'auth.*'], function ($view) {
            $view->with('site', Setting::site());
        });

        // Menu kategori + jumlah keranjang untuk navbar toko.
        View::composer('components.shop-navbar', ShopNavComposer::class);
    }
}