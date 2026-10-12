<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($site['meta_title'] ?? null) ?: ($site['site_name'] ?? config('app.name')))</title>
    <meta name="description" content="@yield('description', $site['meta_description'] ?? ($site['tagline'] ?? ''))">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="shop-ui min-h-screen bg-stone-50 font-sans text-stone-900 antialiased">
    <x-navbar />

    <main>
        @yield('content')
    </main>

    <x-footer />
    <x-whatsapp-float />

    @stack('scripts')
</body>
</html>
