<x-guest-layout>
    <x-slot:headline>Aktifkan verifikasi 2 langkah untuk akun admin.</x-slot:headline>

    <p class="eyebrow">Keamanan akun</p>
    <h1 class="heading mt-2 text-[2rem] leading-tight">Pasang authenticator</h1>
    <p class="mt-2 text-[15px] text-stone-600">Akun panel admin wajib memakai verifikasi 2 langkah.</p>

    <ol class="mt-6 list-decimal space-y-2 pl-5 text-[15px] text-stone-700">
        <li>Pasang Google Authenticator, Authy, atau 1Password di HP.</li>
        <li>Pindai QR di bawah (atau masukkan kunci manual).</li>
        <li>Ketik kode 6 digit yang muncul, lalu simpan kode pemulihan.</li>
    </ol>

    <div class="mt-6 flex flex-col items-center gap-3 rounded-xl border border-stone-200 bg-white p-4">
        <div id="qr" class="h-48 w-48" role="img" aria-label="QR code untuk aplikasi authenticator"></div>
        <p class="text-xs text-stone-500">Atau masukkan kunci ini manual:</p>
        <code class="select-all break-all rounded bg-stone-100 px-2 py-1 text-sm tracking-wider">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
        <a href="{{ $uri }}" class="text-xs text-orange-700 hover:underline">Buka di aplikasi authenticator (di HP)</a>
    </div>

    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6 space-y-5">
        @csrf
        <x-auth-input name="code" label="Kode 6 digit" icon="lock" inputmode="numeric" autocomplete="one-time-code"
                      maxlength="7" required :invalid="$errors->has('code')" :messages="$errors->get('code')" />
        <button type="submit" class="auth-btn">Aktifkan</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-8">
        @csrf
        <button type="submit" class="text-sm text-stone-600 hover:underline">Keluar</button>
    </form>

    <script src="{{ asset('vendor/qrcode.js') }}"></script>
    <script>
        (function () {
            var qr = qrcode(0, 'M');
            qr.addData(@js($uri));
            qr.make();
            document.getElementById('qr').innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
        })();
    </script>
</x-guest-layout>
