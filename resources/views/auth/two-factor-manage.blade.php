<x-guest-layout>
    <x-slot:headline>Akun Anda terlindungi verifikasi 2 langkah.</x-slot:headline>

    <p class="eyebrow">Keamanan akun</p>
    <h1 class="heading mt-2 text-[2rem] leading-tight">Verifikasi 2 langkah aktif</h1>

    @if (session('recovery_codes'))
        <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">Simpan kode pemulihan ini sekarang</p>
            <p class="mt-1 text-sm text-amber-900">Tidak akan ditampilkan lagi. Pakai bila HP hilang; tiap kode berlaku sekali.</p>
            <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                @foreach (session('recovery_codes') as $code)
                    <li class="select-all rounded bg-white px-2 py-1">{{ $code }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="mt-6 text-sm text-stone-600">Sisa kode pemulihan: <strong>{{ $codesLeft }}</strong></p>

    <form method="POST" action="{{ route('two-factor.recovery') }}" class="mt-6 space-y-4 rounded-xl border border-stone-200 bg-white p-4">
        @csrf
        <p class="text-sm font-medium">Buat ulang kode pemulihan</p>
        <x-auth-input name="password" label="Password" type="password" icon="lock" toggle required
                      autocomplete="current-password" :messages="$errors->get('password')" />
        <button type="submit" class="auth-btn">Buat kode baru</button>
    </form>

    <form method="POST" action="{{ route('two-factor.reset') }}" class="mt-6 space-y-4 rounded-xl border border-stone-200 bg-white p-4"
          onsubmit="return confirm('Ganti authenticator? Anda akan memasang ulang dari awal.')">
        @csrf
        <p class="text-sm font-medium">Ganti HP / authenticator</p>
        <x-auth-input name="password" label="Password" type="password" icon="lock" toggle required id="reset_password"
                      autocomplete="current-password" :messages="$errors->reset->get('password')" />
        <x-auth-input name="code" label="Kode authenticator saat ini" icon="lock" inputmode="numeric" maxlength="7" required
                      id="reset_code" :messages="$errors->reset->get('code')" />
        <button type="submit" class="auth-btn">Atur ulang</button>
    </form>

    <a href="{{ route('dashboard') }}" class="mt-8 inline-block text-sm text-stone-600 hover:underline">&larr; Kembali ke dashboard</a>
</x-guest-layout>
