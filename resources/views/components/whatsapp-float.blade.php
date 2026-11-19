@php $wa = !empty($site['contact_whatsapp']) ? preg_replace('/\D/', '', $site['contact_whatsapp']) : null; @endphp
@if($wa)
    <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Halo ' . ($site['site_name'] ?? '') . ', saya ingin bertanya.') }}"
       target="_blank" rel="noopener noreferrer" aria-label="Chat WhatsApp"
       class="fixed bottom-[max(1rem,env(safe-area-inset-bottom))] right-4 z-40 flex h-12 w-12 items-center sm:bottom-5 sm:right-5 sm:h-14 sm:w-14 justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-black/20 transition hover:scale-105 hover:shadow-xl">
        <x-ui-icon name="whatsapp" class="h-6 w-6 sm:h-7 sm:w-7" />
    </a>
@endif
