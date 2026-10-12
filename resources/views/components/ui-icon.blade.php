@props(['name'])
@php
    $paths = [
        'pin'       => '<path d="M12 21s7-6.2 7-11.2A7 7 0 0 0 5 9.8C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.8" r="2.4"/>',
        'mail'      => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M4 7.5l8 6 8-6"/>',
        'phone'     => '<path d="M5 4.5h3.2l1.6 4-2 1.3a11 11 0 0 0 5.4 5.4l1.3-2 4 1.6V18a2 2 0 0 1-2 2A14 14 0 0 1 3 6.5a2 2 0 0 1 2-2z"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'whatsapp'  => '<path d="M4 20l1.3-4.2A8.2 8.2 0 1 1 8.3 18.8L4 20z"/><path d="M9.2 8.6c0 3 2.3 5.6 5.4 6.2l1.3-1.3-2-1-.9.8c-1-.4-1.9-1.3-2.3-2.3l.8-.9-1-2-1.3 1.5z"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".6"/>',
        'linkedin'  => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M8 10.5V16M12 16v-5.5M12 13a2.5 2.5 0 0 1 5 0v3"/><circle cx="8" cy="7.8" r=".6"/>',
        'tiktok'    => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4c.3 2.4 1.9 3.9 4.5 4.1"/>',
        'facebook'  => '<path d="M14 8h2.5V4.5H14A3.5 3.5 0 0 0 10.5 8v2H8v3.5h2.5V20H14v-6.5h2.3l.7-3.5H14V8.5"/>',
        'receipt'   => '<path d="M6 3.5h12v17l-2.5-1.5-2.3 1.5L11 19l-2.3 1.5L6 19V3.5z"/><path d="M9 8h6M9 12h6"/>',
        'user'      => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c.8-3.6 3.5-5.5 7-5.5s6.2 1.9 7 5.5"/>',
        'store'     => '<path d="M4 9l1.2-4.5h13.6L20 9"/><path d="M4 9a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 6 0 2.5 2.5 0 0 0 5 0"/><path d="M5.5 11.5V20h13v-8.5"/>',
        'chat'      => '<path d="M4 5.5h16v10.5H9.5L5.5 19.5V16H4V5.5z"/>',
        'youtube'   => '<rect x="3" y="6" width="18" height="12" rx="3.5"/><path d="M10.5 9.5v5l4-2.5-4-2.5z"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
