<x-guest-layout>
    <x-slot:headline>Selamat datang kembali. Pesanan dan garansi Anda menunggu.</x-slot:headline>

    @php
        $googleOn = true; //  demo TA
        $loginError = $errors->first('email') ?: $errors->first('password');
    @endphp

    <div x-data="{ loading: false }" @pageshow.window="loading = false"
         x-init="if (window.matchMedia('(min-width: 1024px)').matches) document.getElementById('email')?.focus()">

        <p class="eyebrow auth-rise">Masuk</p>
        <h1 class="heading auth-rise mt-2 text-[2rem] leading-tight" style="--d:60ms">Masuk ke akun Anda</h1>
        <p class="auth-rise mb-7 mt-2 text-[15px] text-stone-600" style="--d:120ms">Pantau pesanan, pengiriman, dan garansi dari satu tempat.</p>

        <x-auth-session-status class="mb-5" :status="session('status')" />

        {{-- Satu pesan galat untuk kredensial salah / dibatasi: tidak membocorkan apakah email terdaftar --}}
        @if ($loginError)
            <div class="auth-alert mb-5" role="alert">
                <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16v.1"/></svg>
                <div>
                    <p class="font-medium">{{ $loginError }}</p>
                    @if ($googleOn && str_contains($loginError, 'password salah'))
                        <p class="mt-0.5">Dulu mendaftar dengan Google? Pakai tombol “Lanjutkan dengan Google” di bawah.</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="auth-rise" style="--d:180ms">
            <form method="POST" action="{{ route('login') }}" class="space-y-5" @submit="loading = true">
                @csrf

                <x-auth-input name="email" label="Email" type="email" icon="mail"
                              :value="old('email')" :invalid="(bool) $loginError"
                              required autocomplete="username" inputmode="email" />

                <x-auth-input name="password" label="Password" type="password" icon="lock" toggle
                              :invalid="(bool) $loginError" required autocomplete="current-password">
                    @if (Route::has('password.request'))
                        <x-slot:aside>
                            <a href="{{ route('password.request') }}" class="rounded text-[13px] font-medium text-orange-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">Lupa password?</a>
                        </x-slot:aside>
                    @endif
                </x-auth-input>

                <label for="remember_me" class="-my-1 inline-flex min-h-[44px] cursor-pointer items-center gap-2.5 text-sm text-stone-700">
                    <input id="remember_me" type="checkbox" name="remember" class="h-5 w-5 rounded border-stone-400 text-orange-500 focus:ring-orange-500">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="auth-btn group" :disabled="loading" :aria-busy="loading.toString()">
                    <svg x-show="loading" x-cloak class="auth-spin h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9"/></svg>
                    <span x-text="loading ? 'Memproses…' : 'Masuk'">Masuk</span>
                    <svg x-show="!loading" class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            @include('auth.partials.google-button')

            <p class="mt-8 text-center text-sm text-stone-600">
                Belum punya akun?
                <a href="{{ route('register') }}" class="rounded font-medium text-orange-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">Daftar gratis</a>
            </p>
        </div>
    </div>
</x-guest-layout>
