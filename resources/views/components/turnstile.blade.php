{{-- Widget Cloudflare Turnstile. Tidak tampil bila kunci belum diisi di .env. --}}
@if(\App\Rules\Turnstile::enabled())
    <div>
        <div class="cf-turnstile"
             data-sitekey="{{ config('services.turnstile.site_key') }}"
             data-theme="light"
             data-language="id"
             data-size="flexible"
             data-retry="auto"
             data-retry-interval="4000"
             data-refresh-expired="auto"
             data-callback="koffTurnstileOk"
             data-error-callback="koffTurnstileError"></div>

        {{-- Muncul bila widget gagal tersambung. Kode 110xxx = pengaturan domain/kunci di Cloudflare, bukan masalah pengunjung. --}}
        <div id="cf-ts-fallback" class="auth-error" role="alert" hidden>
            <span data-msg="config" hidden>Verifikasi keamanan belum cocok dengan domain situs ini (kode: <span data-code>-</span>). Mohon hubungi admin toko.</span>
            <span data-msg="client" hidden>Verifikasi keamanan belum bisa dimuat (kode: <span data-code>-</span>). Matikan ad-blocker atau VPN untuk situs ini, lalu
                <button type="button" onclick="window.turnstile && window.turnstile.reset()" class="inline-flex min-h-[44px] items-center text-sm" style="text-decoration:underline;font-weight:600;">muat ulang verifikasi</button>.</span>
        </div>

        @error('cf-turnstile-response')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror
    </div>
    @once
        <script>
            window.koffTurnstileError = function (code) {
                console.warn('[Turnstile] error', code);
                var box = document.getElementById('cf-ts-fallback');
                if (box) {
                    var isConfig = String(code).indexOf('110') === 0;
                    box.hidden = false;
                    box.querySelectorAll('[data-msg]').forEach(function (m) { m.hidden = (m.getAttribute('data-msg') === 'config') !== isConfig; });
                    box.querySelectorAll('[data-code]').forEach(function (c) { c.textContent = code; });
                }
                return false; // biarkan widget menampilkan pesan bawaannya
            };
            window.koffTurnstileOk = function () {
                var box = document.getElementById('cf-ts-fallback');
                if (box) box.hidden = true;
            };
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
