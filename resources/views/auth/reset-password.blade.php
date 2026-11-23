<x-guest-layout>
    <x-slot:headline>Buat password baru untuk akun Anda.</x-slot:headline>

    <div x-data="{ loading: false }" @pageshow.window="loading = false">

        <p class="eyebrow auth-rise">Password baru</p>
        <h1 class="heading auth-rise mt-2 text-[2rem] leading-tight" style="--d:60ms">Buat password baru</h1>
        <p class="auth-rise mb-7 mt-2 text-[15px] text-stone-600" style="--d:120ms">Gunakan minimal 8 karakter. Setelah disimpan, Anda bisa langsung masuk.</p>

        <div class="auth-rise" style="--d:180ms">
            <form method="POST" action="{{ route('password.store') }}" class="space-y-5" @submit="loading = true">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <x-auth-input name="email" label="Email" type="email" icon="mail"
                              :value="old('email', $request->email)" :invalid="$errors->has('email')" :messages="$errors->get('email')"
                              required readonly autocomplete="username" inputmode="email" />

                <x-auth-input name="password" label="Password baru" type="password" icon="lock" toggle
                              :invalid="$errors->has('password')" :messages="$errors->get('password')"
                              required minlength="8" autocomplete="new-password" />

                <x-auth-input name="password_confirmation" label="Konfirmasi password baru" type="password" icon="lock" toggle
                              :invalid="$errors->has('password_confirmation')" :messages="$errors->get('password_confirmation')"
                              required minlength="8" autocomplete="new-password" />

                <button type="submit" class="auth-btn group" :disabled="loading" :aria-busy="loading.toString()">
                    <span x-text="loading ? 'Menyimpan…' : 'Simpan password baru'">Simpan password baru</span>
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-stone-600">
                <a href="{{ route('login') }}" class="rounded font-medium text-copper-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-500">Kembali ke halaman masuk</a>
            </p>
        </div>
    </div>
</x-guest-layout>
