<?php

namespace App\Providers;

use App\Models\Setting;
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
    }
}