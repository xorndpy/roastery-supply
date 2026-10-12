@php $wa = !empty($site['contact_whatsapp']) ? preg_replace('/\D/', '', $site['contact_whatsapp']) : null; @endphp
@if($wa)
    <a href="https://wa.me/{{ $wa }}?text={{ urlencode('Halo ' . ($site['site_name'] ?? '') . ', saya ingin bertanya.') }}"
       target="_blank" rel="noopener noreferrer" aria-label="Chat WhatsApp"
       class="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-black/20 transition hover:scale-105 hover:shadow-xl">
        <x-ui-icon name="whatsapp" class="h-7 w-7" />
    </a>
@endif
