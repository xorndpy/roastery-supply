<x-guest-layout>
    <x-slot:headline>Satu langkah lagi untuk melindungi panel admin.</x-slot:headline>

    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">
        <p class="eyebrow">Verifikasi 2 langkah</p>
        <h1 class="heading mt-2 text-[2rem] leading-tight">Masukkan kode</h1>

        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-5">
            @csrf

            <div x-show="!recovery">
                <p class="mb-4 text-[15px] text-stone-600">Buka aplikasi authenticator di HP Anda, lalu masukkan kode 6 digit.</p>
                <x-auth-input name="code" label="Kode authenticator" icon="lock" inputmode="numeric"
                              autocomplete="one-time-code" maxlength="7" x-bind:disabled="recovery"
                              :invalid="$errors->has('code')" :messages="$errors->get('code')" autofocus />
            </div>

            <div x-show="recovery" x-cloak>
                <p class="mb-4 text-[15px] text-stone-600">Masukkan salah satu kode pemulihan. Setiap kode hanya berlaku sekali.</p>
                <x-auth-input name="recovery_code" label="Kode pemulihan" icon="lock" autocomplete="off"
                              x-bind:disabled="!recovery"
                              :invalid="$errors->has('recovery_code')" :messages="$errors->get('recovery_code')" />
            </div>

            <button type="submit" class="auth-btn">Verifikasi</button>

            <button type="button" class="text-sm font-medium text-orange-700 hover:underline"
                    @click="recovery = !recovery"
                    x-text="recovery ? 'Pakai kode authenticator' : 'Pakai kode pemulihan'"></button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="text-sm text-stone-600 hover:underline">Keluar</button>
        </form>
    </div>
</x-guest-layout>
