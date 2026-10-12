<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-ui bg-stone-50 font-sans antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex min-h-screen">

        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 w-60 transform bg-[#211812] text-stone-100 transition-transform duration-300 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0">
            @include('components.sidebar')
        </aside>

        {{-- Overlay (mobile) --}}
        <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-cloak></div>

        <div class="flex min-w-0 flex-1 flex-col">

            {{-- Topbar --}}
            <header class="sticky top-0 z-30 border-b border-stone-200 bg-white">
                <div class="flex h-14 items-center justify-between px-4 sm:px-7">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-stone-600 lg:hidden" aria-label="Menu">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>

                    <h1 class="text-sm font-semibold text-stone-900">@yield('title', 'Dashboard')</h1>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 text-sm text-stone-600 hover:text-stone-900">
                            {{ auth()->user()->name ?? 'Admin' }}
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 overflow-hidden rounded-lg border border-stone-200 bg-white py-1 shadow-lg shadow-stone-900/5">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Profil</a>
                            <a href="{{ route('home') }}" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Lihat Toko</a>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-100">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-stone-50">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Konten --}}
            <main class="flex-1 p-4 sm:p-7">
                @if(session('success'))
                    <div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>

            <footer class="border-t border-stone-200 bg-white px-7 py-4 text-xs text-stone-500">
                &copy; {{ date('Y') }} {{ config('app.name') }} — Admin Panel
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
