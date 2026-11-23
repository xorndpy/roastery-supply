<x-guest-layout>
    <x-slot:headline>Lupa password? Kami kirim tautan untuk mengatur ulang.</x-slot:headline>

    <div x-data="{ loading: false }" @pageshow.window="loading = false"
         x-init="if (window.matchMedia('(min-width: 1024px)').matches) document.getElementById('email')?.focus()">

        <p class="eyebrow auth-rise">Lupa password</p>
        <h1 class="heading auth-rise mt-2 text-[2rem] leading-tight" style="--d:60ms">Atur ulang password</h1>
        <p class="auth-rise mb-7 mt-2 text-[15px] text-stone-600" style="--d:120ms">Masukkan email akun Anda. Kami kirim tautan untuk membuat password baru ke email itu.</p>

        <x-auth-session-status class="mb-5" :status="session('status')" />

        <div class="auth-rise" style="--d:180ms">
            <form method="POST" action="{{ route('password.email') }}" class="space-y-5" @submit="loading = true">
                @csrf

                <x-auth-input name="email" label="Email" type="email" icon="mail"
                              :value="old('email')" :invalid="$errors->has('email')" :messages="$errors->get('email')"
                              required autocomplete="username" inputmode="email" />

                <button type="submit" class="auth-btn group" :disabled="loading" :aria-busy="loading.toString()">
                    <span x-text="loading ? 'Mengirim…' : 'Kirim tautan atur ulang'">Kirim tautan atur ulang</span>
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-stone-600">
                Ingat password Anda?
                <a href="{{ route('login') }}" class="rounded font-medium text-copper-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-500">Kembali masuk</a>
            </p>
        </div>
    </div>
</x-guest-layout>
