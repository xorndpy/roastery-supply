<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-stone-100 font-sans antialiased">
    <div x-data="{ sidebarOpen: false }" class="min-h-screen flex">

        {{-- Sidebar --}}
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-stone-900 text-stone-100
                   transform transition-transform duration-300 lg:translate-x-0 lg:static lg:z-auto">
            @include('components.sidebar')
        </aside>

        {{-- Overlay (mobile) --}}
        <div
            x-show="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-black/50 z-40 lg:hidden"
            x-cloak></div>

        {{-- Main Content --}}
        <div class="flex-1 flex flex-col min-w-0">

            {{-- Topbar --}}
            <header class="bg-white border-b border-stone-200 sticky top-0 z-30">
                <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

                    {{-- Hamburger (mobile) --}}
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-stone-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    {{-- Page Title --}}
                    <h1 class="text-lg font-semibold text-stone-800">@yield('title', 'Dashboard')</h1>

                    {{-- User Menu --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 text-stone-700 hover:text-stone-900">
                            <span class="text-sm font-medium">{{ auth()->user()->name ?? 'Admin' }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div
                            x-show="open"
                            @click.outside="open = false"
                            x-cloak
                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-stone-200 py-1">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Profil</a>
                            <a href="{{ route('home') }}" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Lihat Toko</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-stone-50">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Content --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>

            {{-- Footer --}}
            <footer class="bg-white border-t border-stone-200 px-6 py-4 text-sm text-stone-500">
                &copy; {{ date('Y') }} {{ config('app.name') }} — Admin Panel
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>