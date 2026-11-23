<x-guest-layout>
    <x-slot:headline>Satu langkah lagi: pastikan email Anda aktif.</x-slot:headline>

    <div x-data="{ loading: false }" @pageshow.window="loading = false">

        <p class="eyebrow auth-rise">Verifikasi email</p>
        <h1 class="heading auth-rise mt-2 text-[2rem] leading-tight" style="--d:60ms">Cek email Anda</h1>
        <p class="auth-rise mt-2 text-[15px] text-stone-600" style="--d:120ms">
            Kami mengirim tautan verifikasi ke <b class="text-stone-900">{{ auth()->user()->email }}</b>.
            Klik tautan itu untuk melanjutkan checkout. Keranjang Anda tetap aman.
        </p>
        <p class="auth-rise mb-6 mt-2 text-[14px] text-stone-500" style="--d:150ms">Tidak ada di kotak masuk? Periksa juga folder <b>Spam</b> atau <b>Promosi</b>.</p>

        @if (session('status') == 'verification-link-sent')
            <x-auth-session-status class="mb-5" status="Tautan verifikasi baru sudah dikirim. Periksa kotak masuk dan folder spam." />
        @endif

        <div class="auth-rise" style="--d:180ms">
            <form method="POST" action="{{ route('verification.send') }}" class="space-y-5" @submit="loading = true">
                @csrf
                <button type="submit" class="auth-btn group" :disabled="loading" :aria-busy="loading.toString()">
                    <span x-text="loading ? 'Mengirim…' : 'Kirim ulang email verifikasi'">Kirim ulang email verifikasi</span>
                </button>
            </form>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-stone-600">
                <a href="{{ route('profile.edit') }}" class="rounded font-medium text-copper-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-500">Salah email? Ubah di profil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded underline hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-500">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
