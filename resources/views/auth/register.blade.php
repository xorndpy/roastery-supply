<x-guest-layout>
    <x-slot:headline>Mulai dari satu akun. Kelola pesanan, pengiriman, dan garansi di sini.</x-slot:headline>

    <div x-data="{
            pw: '', pw2: '', loading: false,
            get score() {
                const p = this.pw;
                if (p.length < 8) return 0;
                let s = 1;
                if (p.length >= 12) s++;
                if (/[a-z]/.test(p) && /[A-Z]/.test(p) && /\d/.test(p)) s++;
                if (/[^A-Za-z0-9]/.test(p)) s++;
                return Math.min(s, 4);
            },
            get label() { return ['Terlalu pendek', 'Cukup', 'Baik', 'Kuat', 'Sangat kuat'][this.score]; },
            get bar() { return ['bg-red-500', 'bg-amber-500', 'bg-lime-600', 'bg-green-600', 'bg-green-700'][this.score]; }
         }"
         @pageshow.window="loading = false"
         x-init="if (window.matchMedia('(min-width: 1024px)').matches) document.getElementById('name')?.focus()">

        <p class="eyebrow auth-rise">Daftar</p>
        <h1 class="heading auth-rise mt-2 text-[2rem] leading-tight" style="--d:60ms">Buat akun baru</h1>
        <p class="auth-rise mb-7 mt-2 text-[15px] text-stone-600" style="--d:120ms">Gratis, selesai kurang dari satu menit.</p>

        <div class="auth-rise" style="--d:180ms">
            <form method="POST" action="{{ route('register') }}" class="space-y-5" @submit="loading = true">
                @csrf

                <x-auth-input name="name" label="Nama lengkap" icon="user" :value="old('name')"
                              :invalid="$errors->has('name')" :messages="$errors->get('name')"
                              required autocomplete="name" />

                <x-auth-input name="email" label="Email" type="email" icon="mail" :value="old('email')"
                              :invalid="$errors->has('email')" :messages="$errors->get('email')"
                              required autocomplete="username" inputmode="email" />

                <x-auth-input name="password" label="Password" type="password" icon="lock" toggle
                              hint="Minimal 8 karakter. Makin panjang dan bervariasi, makin aman."
                              :invalid="$errors->has('password')" :messages="$errors->get('password')"
                              required minlength="8" autocomplete="new-password" x-model="pw">
                    {{-- Meter kekuatan: hanya saran. Aturan wajibnya tetap dicek server (minimal 8 karakter). --}}
                    <div x-show="pw.length" x-cloak class="mt-2.5">
                        <div class="flex gap-1.5" aria-hidden="true">
                            <template x-for="i in 4" :key="i">
                                <span class="h-1.5 flex-1 rounded-full transition-colors duration-300" :class="i <= Math.max(score, 1) ? bar : 'bg-stone-200'"></span>
                            </template>
                        </div>
                        <p class="mt-1.5 text-[13px]" :class="score === 0 ? 'text-red-700' : 'text-stone-700'" role="status">
                            Kekuatan: <span class="font-medium" x-text="label"></span>
                        </p>
                    </div>
                </x-auth-input>

                <x-auth-input name="password_confirmation" label="Konfirmasi password" type="password" icon="lock" toggle
                              :invalid="$errors->has('password_confirmation')" :messages="$errors->get('password_confirmation')"
                              required autocomplete="new-password" x-model="pw2">
                    <p x-show="pw2.length" x-cloak role="status" class="mt-2 flex items-center gap-1.5 text-[13px]" :class="pw2 === pw ? 'text-green-700' : 'text-red-700'">
                        <svg x-show="pw2 === pw" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        <svg x-show="pw2 !== pw" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        <span x-text="pw2 === pw ? 'Password cocok' : 'Belum sama dengan password di atas'"></span>
                    </p>
                </x-auth-input>

                <button type="submit" class="auth-btn group" :disabled="loading" :aria-busy="loading.toString()">
                    <svg x-show="loading" x-cloak class="auth-spin h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9"/></svg>
                    <span x-text="loading ? 'Membuat akun…' : 'Daftar'">Daftar</span>
                    <svg x-show="!loading" class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            @include('auth.partials.google-button')

            <p class="mt-8 text-center text-sm text-stone-600">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="rounded font-medium text-orange-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">Masuk</a>
            </p>
        </div>
    </div>
</x-guest-layout>
