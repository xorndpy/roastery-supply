{{-- Bar mengambang: muncul setelah ada produk yang dipilih untuk dibandingkan. --}}
<div x-data="{ count: {{ count((array) session('compare', [])) }},
        async clear() {
            await fetch(@js(route('compare.clear')), { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } });
            this.count = 0; window.dispatchEvent(new CustomEvent('compare:changed', { detail: { count: 0 } }));
            document.querySelectorAll('input[aria-label^=\'Bandingkan\']').forEach(i => { i.checked = false; });
        } }"
     @compare:changed.window="count = $event.detail.count"
     x-show="count > 0" x-cloak
     class="fixed bottom-4 left-1/2 z-40 flex w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 items-center justify-between gap-3 rounded-xl bg-stone-900 px-4 py-3 text-sm text-white shadow-xl max-sm:bottom-20"
     role="status">
    <span><b x-text="count"></b> produk dipilih</span>
    <span class="flex items-center gap-3">
        <button type="button" @click="clear()" class="text-white/70 underline-offset-2 hover:underline">Kosongkan</button>
        <a x-show="count >= 2" href="{{ route('compare.index') }}" class="rounded-lg border border-white px-3 py-1.5 font-semibold hover:bg-white hover:text-stone-900">Bandingkan</a>
        <span x-show="count < 2" class="text-white/70">Pilih minimal 2</span>
    </span>
</div>
