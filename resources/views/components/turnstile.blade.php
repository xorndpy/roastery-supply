{{-- Widget Cloudflare Turnstile. Tidak tampil bila kunci belum diisi di .env. --}}
@if(\App\Rules\Turnstile::enabled())
    <div>
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light" data-language="id"></div>
        @error('cf-turnstile-response')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror
    </div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
