<div x-data="{ atStart: true, atEnd: true,
                update() { const t = this.$refs.t; this.atStart = t.scrollLeft <= 4; this.atEnd = t.scrollLeft + t.clientWidth >= t.scrollWidth - 4; },
                go(d) { this.$refs.t.scrollBy({ left: d * this.$refs.t.clientWidth * 0.8, behavior: 'smooth' }) } }"
     x-init="$nextTick(() => update())" @resize.window.debounce.100ms="update()" class="relative">
    <div x-ref="t" @scroll.passive.debounce.50ms="update()" class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {{ $slot }}
    </div>
    <button type="button" x-show="!atStart" x-cloak @click="go(-1)" aria-label="Sebelumnya" class="absolute -left-4 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-800 shadow-md hover:border-orange-500 md:flex">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
    </button>
    <button type="button" x-show="!atEnd" x-cloak @click="go(1)" aria-label="Berikutnya" class="absolute -right-4 top-1/2 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-800 shadow-md hover:border-orange-500 md:flex">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
    </button>
</div>
