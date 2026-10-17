@php
    $wa = !empty($site['contact_whatsapp']) ? preg_replace('/\D/', '', $site['contact_whatsapp']) : null;
    $socials = array_filter([
        'whatsapp'  => $wa ? 'https://wa.me/' . $wa : null,
        'instagram' => $site['social_instagram'] ?? null,
        'tiktok'    => $site['social_tiktok'] ?? null,
        'linkedin'  => $site['social_linkedin'] ?? null,
        'facebook'  => $site['social_facebook'] ?? null,
        'youtube'   => $site['social_youtube'] ?? null,
    ]);
    $nav = [['Beranda', 'home'], ['Katalog', 'products.index'], ['Tentang Kami', 'about'], ['Cabang', 'branches.index'], ['FAQ', 'faq'], ['Kontak', 'contact.index']];
    $contacts = array_filter([
        ['pin',   $site['contact_address'] ?? null, null],
        ['mail',  $site['contact_email'] ?? null, !empty($site['contact_email']) ? 'mailto:' . $site['contact_email'] : null],
        ['phone', $site['contact_phone'] ?? null, !empty($site['contact_phone']) ? 'tel:' . preg_replace('/[^0-9+]/', '', $site['contact_phone']) : null],
    ], fn ($c) => filled($c[1]));
@endphp

<footer class="mt-20 border-t border-stone-200 bg-white">
    <div class="container-x grid gap-12 py-14 md:grid-cols-[1.5fr_1fr_1.4fr]">

        {{-- Brand + sosial --}}
        <div>
            @if(!empty($site['site_logo']))
                <img src="{{ asset('storage/' . $site['site_logo']) }}" alt="{{ $site['site_name'] }}" class="h-10 w-auto">
            @else
                <div class="heading text-2xl text-brand">{{ $site['site_name'] }}</div>
            @endif
            @if(!empty($site['tagline']))
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-stone-600">{{ $site['tagline'] }}</p>
            @endif

            @if($socials)
                <div class="mt-6 flex flex-wrap gap-2.5">
                    @foreach($socials as $name => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ ucfirst($name) }}"
                           class="flex h-11 w-11 items-center justify-center rounded-full border border-stone-200 bg-white text-orange-600 shadow-sm transition hover:-translate-y-0.5 hover:border-orange-500 hover:bg-orange-50">
                            <x-ui-icon :name="$name" class="h-5 w-5" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Navigasi --}}
        <div>
            <h4 class="mb-4 text-xs font-semibold uppercase tracking-[0.12em] text-stone-900">Navigasi</h4>
            <ul class="space-y-2.5 text-sm">
                @foreach($nav as [$label, $route])
                    <li><a href="{{ route($route) }}" class="text-stone-600 transition hover:text-orange-600">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>

        {{-- Kontak --}}
        <div>
            <h4 class="mb-4 text-xs font-semibold uppercase tracking-[0.12em] text-stone-900">Hubungi Kami</h4>
            <ul class="space-y-3.5 text-sm">
                @forelse($contacts as [$icon, $text, $href])
                    <li class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-stone-200 bg-white text-orange-500">
                            <x-ui-icon :name="$icon" class="h-[18px] w-[18px]" />
                        </span>
                        @if($href)
                            <a href="{{ $href }}" @if(str_starts_with($href, 'http')) target="_blank" rel="noopener noreferrer" @endif
                               class="self-center break-all text-stone-700 transition hover:text-orange-600">{{ $text }}</a>
                        @else
                            <span class="self-center whitespace-pre-line leading-relaxed text-stone-700">{{ $text }}</span>
                        @endif
                    </li>
                @empty
                    <li><a href="{{ route('contact.index') }}" class="text-stone-700 hover:text-orange-600">Kirim pesan kepada kami</a></li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="border-t border-stone-200">
        <div class="container-x py-5 text-xs text-stone-500">
            &copy; {{ date('Y') }} {{ $site['site_name'] }}. Seluruh hak cipta dilindungi.
        </div>
    </div>
</footer>
