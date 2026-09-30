<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description', 'Mesin kopi profesional untuk kedai kopi serius.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 font-sans antialiased text-stone-900">
    <x-navbar />

    <main>
        @yield('content')
    </main>

    <x-footer />

    @stack('scripts')
</body>
</html>