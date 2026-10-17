@php
    $siteName = $site['site_name'] ?? config('app.name');
    $userName = auth()->user()->name ?? 'Admin';
    $initials = collect(preg_split('/\s+/', trim($userName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ $siteName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Dipakai halaman Laporan. Dashboard memakai grafik SVG sendiri. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="admin-ui bg-stone-50 font-sans antialiased">
    <a href="#main" class="sr-only z-[60] rounded-md bg-white px-4 py-2 text-sm font-medium text-brand shadow focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten</a>

    <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="flex min-h-screen">

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
                <div class="flex h-14 items-center gap-3 px-4 sm:px-7">
                    <button @click="sidebarOpen = !sidebarOpen" class="-ml-1 flex h-9 w-9 items-center justify-center rounded-md text-stone-600 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 lg:hidden" aria-label="Buka menu">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>

                    {{-- Judul hanya di mobile; di desktop judul sudah ada di halaman --}}
                    <p class="text-sm font-semibold text-stone-900 lg:hidden">@yield('title', 'Dashboard')</p>

                    {{-- Pencarian menu (Ctrl+K) --}}
                    <button type="button" @click="$dispatch('open-palette')"
                            class="group hidden h-9 w-full max-w-sm items-center gap-2.5 rounded-md border border-stone-200 bg-stone-50 px-3 text-left text-sm text-stone-500 transition hover:border-stone-300 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 lg:flex">
                        <svg class="h-4 w-4 shrink-0 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>
                        <span class="flex-1">Cari menu atau halaman…</span>
                        <span class="flex items-center gap-1"><kbd class="rounded border border-stone-300 bg-white px-1.5 py-0.5 font-sans text-[10px] text-stone-600">Ctrl</kbd><kbd class="rounded border border-stone-300 bg-white px-1.5 py-0.5 font-sans text-[10px] text-stone-600">K</kbd></span>
                    </button>

                    <div class="ml-auto flex items-center gap-1.5 sm:gap-3">
                        <button type="button" @click="$dispatch('open-palette')" class="flex h-9 w-9 items-center justify-center rounded-md text-stone-600 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 lg:hidden" aria-label="Cari menu">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>
                        </button>

                        <a href="{{ route('home') }}" class="hidden items-center gap-1 rounded-md px-2.5 py-1.5 text-sm text-stone-600 transition hover:bg-stone-100 hover:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 sm:inline-flex">
                            Lihat toko
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
                        </a>

                        <div x-data="{ open: false }" @keydown.escape="open = false" class="relative">
                            <button @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu"
                                    class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-2.5 text-sm text-stone-700 transition hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand text-[12px] font-semibold tracking-wide text-orange-100" aria-hidden="true">{{ $initials }}</span>
                                <span class="hidden max-w-[10rem] truncate sm:block">{{ $userName }}</span>
                                <svg class="h-3.5 w-3.5 text-stone-500 transition-transform duration-200" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div x-show="open" @click.outside="open = false" x-cloak role="menu"
                                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute right-0 mt-2 w-52 overflow-hidden rounded-lg border border-stone-200 bg-white py-1 shadow-lg shadow-stone-900/5">
                                <div class="border-b border-stone-100 px-4 py-2.5">
                                    <p class="truncate text-sm font-medium text-stone-900">{{ $userName }}</p>
                                    <p class="truncate text-xs text-stone-500">{{ auth()->user()->email ?? '' }}</p>
                                </div>
                                <a href="{{ route('profile.edit') }}" role="menuitem" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Profil</a>
                                <a href="{{ route('home') }}" role="menuitem" class="block px-4 py-2 text-sm text-stone-700 hover:bg-stone-50 sm:hidden">Lihat toko</a>
                                <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-100">
                                    @csrf
                                    <button type="submit" role="menuitem" class="w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-stone-50">Keluar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Konten --}}
            <main id="main" class="flex-1 p-4 sm:p-7">
                @if(session('success'))
                    <div class="mb-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>

            <footer class="border-t border-stone-200 bg-white px-7 py-4 text-xs text-stone-500">
                &copy; {{ date('Y') }} {{ $siteName }} — Admin Panel
            </footer>
        </div>
    </div>

    {{-- Pencarian menu: mengumpulkan tautan dari sidebar, jadi otomatis ikut hak akses user --}}
    <div x-data="adminPalette()" @open-palette.window="show()" @keydown.window="onGlobalKey($event)" x-cloak>
        <div x-show="open" class="fixed inset-0 z-[70] flex items-start justify-center px-4 pt-[14vh]" role="dialog" aria-modal="true" aria-label="Cari menu">
            <div x-show="open" x-transition.opacity.duration.150ms @click="close()" class="absolute inset-0 bg-stone-950/50"></div>
            <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-2 scale-[.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 class="relative w-full max-w-lg overflow-hidden rounded-xl border border-stone-200 bg-white shadow-2xl shadow-stone-950/20">
                <div class="flex items-center gap-3 border-b border-stone-200 px-4">
                    <svg class="h-5 w-5 shrink-0 text-stone-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>
                    <input x-ref="q" x-model="q" @keydown="onKey($event)" type="text" role="combobox" aria-expanded="true" aria-controls="palette-list" :aria-activedescendant="results.length ? 'pal-' + active : null"
                           placeholder="Ketik nama menu, mis. pesanan, stok, voucher…" autocomplete="off" spellcheck="false"
                           class="h-14 w-full border-0 bg-transparent px-0 text-[15px] text-stone-900 placeholder:text-stone-500 focus:ring-0">
                    <kbd class="rounded border border-stone-300 px-1.5 py-0.5 font-sans text-[10px] text-stone-600">Esc</kbd>
                </div>
                <ul id="palette-list" role="listbox" class="max-h-[50vh] overflow-y-auto p-2">
                    <template x-for="(r, i) in results" :key="r.href">
                        <li :id="'pal-' + i" role="option" :aria-selected="(i === active).toString()">
                            <a :href="r.href" @mousemove="active = i"
                               :class="i === active ? 'bg-stone-100 text-stone-900' : 'text-stone-700'"
                               class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm">
                                <span x-text="r.label" class="font-medium"></span>
                                <span x-text="r.group" class="text-xs text-stone-500"></span>
                            </a>
                        </li>
                    </template>
                    <li x-show="!results.length" class="px-3 py-8 text-center text-sm text-stone-500">Tidak ada menu yang cocok.</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function adminPalette() {
            return {
                open: false, q: '', active: 0, items: [],
                get results() {
                    const q = this.q.trim().toLowerCase();
                    return !q ? this.items : this.items.filter(i => (i.label + ' ' + i.group).toLowerCase().includes(q));
                },
                show() {
                    this.items = [...document.querySelectorAll('aside [data-nav]')].map(a => ({ label: a.dataset.navLabel, group: a.dataset.navGroup, href: a.href }));
                    this.q = ''; this.active = 0; this.open = true;
                    this.$nextTick(() => this.$refs.q.focus());
                },
                close() { this.open = false; },
                onGlobalKey(e) {
                    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.open ? this.close() : this.show(); }
                    else if (e.key === 'Escape' && this.open) this.close();
                },
                onKey(e) {
                    const n = this.results.length; if (!n) return;
                    if (e.key === 'ArrowDown') { e.preventDefault(); this.active = (this.active + 1) % n; this.scroll(); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); this.active = (this.active - 1 + n) % n; this.scroll(); }
                    else if (e.key === 'Enter') { e.preventDefault(); window.location.href = this.results[this.active].href; }
                    else this.active = 0;
                },
                scroll() { this.$nextTick(() => document.getElementById('pal-' + this.active)?.scrollIntoView({ block: 'nearest' })); }
            };
        }
    </script>

    @stack('scripts')
</body>
</html>
