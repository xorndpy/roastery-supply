<nav x-data="{ open: false }" class="bg-stone-50 border-b border-stone-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            
            {{-- Logo --}}
            <a href="{{ route('home') }}" class="text-xl font-bold tracking-tight text-stone-900">
                ROASTERY<span class="text-orange-500">.</span>
            </a>

            {{-- Menu Desktop --}}
            <div class="hidden md:flex items-center gap-8">
                <a href="{{ route('home') }}" class="text-sm text-stone-700 hover:text-orange-500">Beranda</a>
                <a href="{{ route('products.index') }}" class="text-sm text-stone-700 hover:text-orange-500">Katalog</a>
                <a href="{{ route('about') }}" class="text-sm text-stone-700 hover:text-orange-500">Tentang</a>
                <a href="{{ route('contact.index') }}" class="text-sm text-stone-700 hover:text-orange-500">Kontak</a>
                <a href="{{ route('faq') }}" class="text-sm text-stone-700 hover:text-orange-500">FAQ</a>
            </div>

            {{-- Right Menu --}}
            <div class="hidden md:flex items-center gap-4">
                {{-- Cart --}}
                <a href="{{ route('customer.cart.index') }}" class="relative text-stone-700 hover:text-orange-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="absolute -top-1 -right-1 bg-orange-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ count((array) session('cart', [])) }}
                    </span>
                </a>

                @auth
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="text-sm text-stone-700 hover:text-orange-500">
                            {{ auth()->user()->name }}
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-stone-200 py-1">
                            <a href="{{ route('customer.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Dashboard</a>
                            <a href="{{ route('customer.orders.index') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Pesanan Saya</a>
                            <a href="{{ route('customer.profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-stone-50">Profil</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-stone-50">Logout</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-sm text-stone-700 hover:text-orange-500">Masuk</a>
                    <a href="{{ route('register') }}" class="text-sm bg-stone-900 text-white px-4 py-2 rounded-lg hover:bg-stone-800">Daftar</a>
                @endauth
            </div>

            {{-- Hamburger (mobile) --}}
            <button @click="open = !open" class="md:hidden text-stone-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="open" @click.outside="open = false" x-cloak class="md:hidden pb-4 space-y-2">
            <a href="{{ route('home') }}" class="block py-2 text-sm text-stone-700">Beranda</a>
            <a href="{{ route('products.index') }}" class="block py-2 text-sm text-stone-700">Katalog</a>
            <a href="{{ route('about') }}" class="block py-2 text-sm text-stone-700">Tentang</a>
            <a href="{{ route('contact.index') }}" class="block py-2 text-sm text-stone-700">Kontak</a>
            <a href="{{ route('faq') }}" class="block py-2 text-sm text-stone-700">FAQ</a>
        </div>
    </div>
</nav>