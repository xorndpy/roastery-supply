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

        {{-- Muncul bila widget gagal tersambung ke Cloudflare. Kode galat membantu mencari penyebabnya. --}}
        <div id="cf-ts-fallback" class="auth-error" role="alert" hidden>
            Verifikasi keamanan belum bisa dimuat (kode: <span data-code>-</span>).
            Matikan ad-blocker atau VPN untuk situs ini, lalu
            <button type="button" onclick="window.turnstile && window.turnstile.reset()" class="inline-flex min-h-[44px] items-center text-sm" style="text-decoration:underline;font-weight:600;">muat ulang verifikasi</button>.
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
                if (box) { box.hidden = false; var s = box.querySelector('[data-code]'); if (s) s.textContent = code; }
                return false; // biarkan widget menampilkan pesan bawaannya dan mencoba ulang otomatis
            };
            window.koffTurnstileOk = function () {
                var box = document.getElementById('cf-ts-fallback');
                if (box) box.hidden = true;
            };
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
