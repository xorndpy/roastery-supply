@php
    // Tag SEO bawaan: canonical, Open Graph, Twitter Card, dan data terstruktur Organization (beranda).
    // Halaman yang sudah punya tag sendiri (mis. detail produk lewat @push('head')) tidak digandakan.
    $name = $site['site_name'] ?? config('app.name');
    $own = str_contains($__env->yieldPushContent('head'), 'og:title');

    $plain = fn ($html) => trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
    $title = $plain($__env->yieldContent('title')) ?: (($site['meta_title'] ?? null) ?: $name);
    $desc = $plain($__env->yieldContent('description')) ?: ($site['meta_description'] ?? ($site['tagline'] ?? ''));
    $type = $plain($__env->yieldContent('og_type')) ?: 'website';

    $page = (int) request()->query('page', 1);
    $url = url()->current() . ($page > 1 ? '?page=' . $page : '');

    $img = $plain($__env->yieldContent('og_image'));
    if ($img === '') {
        $src = ($site['og_image'] ?? null) ?: ($site['site_logo'] ?? null);
        $img = $src ? media_url($src) : '';
    }
@endphp
@unless($own)
    <link rel="canonical" href="{{ $url }}">
    <meta property="og:site_name" content="{{ $name }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title }}">
    @if($desc)<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($desc, 200, '') }}">@endif
    <meta property="og:url" content="{{ $url }}">
    @if($img)<meta property="og:image" content="{{ $img }}">@endif
    <meta name="twitter:card" content="{{ $img ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    @if($desc)<meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($desc, 200, '') }}">@endif
    @if($img)<meta name="twitter:image" content="{{ $img }}">@endif
@endunless

@if(request()->routeIs('home'))
    @php
        $sameAs = array_values(array_filter(array_map(
            fn ($k) => filled($site[$k] ?? null) && preg_match('#^https?://#i', (string) $site[$k]) ? $site[$k] : null,
            ['social_instagram', 'social_facebook', 'social_tiktok', 'social_youtube', 'social_linkedin']
        )));
        $logo = ! empty($site['site_logo']) ? media_url($site['site_logo']) : null;
        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => url('/'),
            'logo' => $logo,
            'description' => $desc ?: null,
            'email' => $site['contact_email'] ?? null,
            'telephone' => $site['contact_phone'] ?? null,
            'address' => ! empty($site['contact_address'])
                ? ['@type' => 'PostalAddress', 'streetAddress' => $site['contact_address'], 'addressCountry' => 'ID']
                : null,
            'sameAs' => $sameAs ?: null,
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
