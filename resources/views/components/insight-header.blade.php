@props(['categories' => [], 'q' => '', 'current' => null])
@php $siteName = $site['site_name'] ?? config('app.name'); @endphp

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-stone-200 bg-white">
    <div class="container-x flex h-[72px] items-center gap-5 lg:gap-8">
        <a href="{{ route('articles.index') }}" class="flex shrink-0 items-center gap-3 leading-none">
            <span class="heading text-lg font-semibold uppercase tracking-[0.22em] text-orange-600 sm:text-xl">{{ $siteName }}</span>
            <span class="h-6 w-px bg-stone-300" aria-hidden="true"></span>
            <span class="text-xs font-medium uppercase tracking-[0.3em] text-stone-500 sm:text-sm">Insight</span>
        </a>

        <form action="{{ route('articles.index') }}" method="GET" role="search" class="hidden w-60 shrink-0 md:block xl:w-72">
            <label for="insight-q" class="sr-only">Cari artikel</label>
            <div class="relative">
                <input id="insight-q" type="search" name="q" value="{{ $q }}" placeholder="Cari artikel, resep, atau topik…" class="h-11 w-full rounded-full border border-stone-200 bg-stone-50 pl-5 pr-14 text-sm placeholder:text-stone-500 focus:border-orange-500 focus:bg-white focus:ring-4 focus:ring-orange-500/15">
                <button class="absolute right-1 top-1 flex h-9 w-9 items-center justify-center rounded-full bg-orange-500 text-white transition hover:bg-orange-600" aria-label="Cari">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4 4"/></svg>
                </button>
            </div>
        </form>

        <nav class="hidden items-center gap-5 text-[15px] xl:flex" aria-label="Kategori artikel">
            @foreach($categories as $c)
                <a href="{{ route('articles.index', ['kategori' => \Illuminate\Support\Str::slug($c)]) }}"
                   class="whitespace-nowrap {{ $current === $c ? 'font-medium text-orange-600' : 'text-stone-600 hover:text-stone-900' }} transition">{{ $c }}</a>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-4">
            <a href="{{ route('home') }}" class="hidden whitespace-nowrap text-sm text-stone-500 transition hover:text-stone-900 sm:inline">Website utama →</a>
            <button @click="open = !open" class="text-stone-800 xl:hidden" aria-label="Menu kategori" :aria-expanded="open">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path x-show="!open" d="M4 7h16M4 12h16M4 17h16"/><path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak x-transition.opacity class="border-t border-stone-200 bg-white xl:hidden">
        <div class="container-x py-3">
            <form action="{{ route('articles.index') }}" method="GET" role="search" class="mb-3 md:hidden">
                <input type="search" name="q" value="{{ $q }}" placeholder="Cari artikel…" aria-label="Cari artikel" class="h-10 w-full rounded-full border border-stone-200 bg-stone-50 px-4 text-sm">
            </form>
            <div class="flex flex-wrap gap-2">
                @foreach($categories as $c)
                    <a href="{{ route('articles.index', ['kategori' => \Illuminate\Support\Str::slug($c)]) }}" class="rounded-full border px-3.5 py-1.5 text-sm {{ $current === $c ? 'border-orange-500 bg-orange-50 text-orange-700' : 'border-stone-200 text-stone-600' }}">{{ $c }}</a>
                @endforeach
            </div>
            <a href="{{ route('home') }}" class="mt-3 block text-sm text-stone-500">Website utama →</a>
        </div>
    </div>
</header>
