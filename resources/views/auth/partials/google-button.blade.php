{{--
    Tombol "Lanjutkan dengan Google".
    Versi demo TA: Selalu tampil, tapi pas diklik cuma muncul alert.
    Kalau mau beneran jalan, ganti onclick jadi href="{{ route('auth.google.redirect') }}"
    dan hapus alert-nya.
--}}

<div x-data="{ go: false }" @pageshow.window="go = false">
    <div class="mb-5 mt-6 flex items-center gap-3 text-xs uppercase tracking-[0.12em] text-stone-600" role="separator">
        <span class="h-px flex-1 bg-stone-200"></span>
        atau
        <span class="h-px flex-1 bg-stone-200"></span>
    </div>

    {{-- Tombol Google: Versi Demo (Alert) --}}
    <button type="button" 
            onclick="alert('Fitur Google Login sedang dalam pengembangan. Silakan gunakan email dan password.')" 
            class="auth-google group w-full flex items-center justify-center gap-3 rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 transition">
        
        <svg class="h-5 w-5 transition-transform duration-200 group-hover:scale-110" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.5 5.4 2.6 13.2l7.9 6.1C12.4 13.6 17.7 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4 7.1-10 7.1-17.5z"/>
            <path fill="#FBBC05" d="M10.5 28.7c-.5-1.5-.8-3-.8-4.7s.3-3.2.8-4.7l-7.9-6.1C.9 16.4 0 20.1 0 24s.9 7.6 2.6 10.8l7.9-6.1z"/>
            <path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.5-5.8c-2.1 1.4-4.9 2.3-8.4 2.3-6.3 0-11.6-4.1-13.5-9.8l-7.9 6.1C6.5 42.6 14.6 48 24 48z"/>
        </svg>
        
        <span>Lanjutkan dengan Google</span>
    </button>
</div>