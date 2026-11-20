@props(['side' => 'right', 'label' => 'Menu utama'])
@php
    $right = $side !== 'left';
    $off = $right ? 'translate-x-full' : '-translate-x-full';
@endphp
{{--
    Panel samping untuk menu mobile (bukan layar penuh), dipakai x-navbar dan x-shop-navbar.
    Memakai state `open` milik <header> induknya. Menutup lewat tombol X, klik area gelap, atau tombol Esc.
    Fokus dikunci di dalam panel selama terbuka (x-focus-trap), dan scroll halaman di belakangnya dimatikan.
    Slot: header (logo/nama), default (daftar menu), footer (opsional, menempel di bawah).
--}}
<div class="fixed inset-0 z-50 lg:hidden" :class="open ? '' : 'pointer-events-none'"
     x-effect="document.body.classList.toggle('overflow-hidden', open)">
    <div x-show="open" x-cloak x-transition.opacity.duration.200ms @click="open = false" class="absolute inset-0 bg-stone-900/50" aria-hidden="true"></div>

    <div x-show="open" x-cloak role="dialog" aria-modal="true" aria-label="{{ $label }}" tabindex="-1" x-focus-trap="open"
         x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none" x-transition:enter-start="{{ $off }}" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none" x-transition:leave-start="translate-x-0" x-transition:leave-end="{{ $off }}"
         class="absolute inset-y-0 {{ $right ? 'right-0' : 'left-0' }} flex w-[72%] max-w-[280px] flex-col bg-white shadow-2xl focus:outline-none">
        <div class="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-stone-200 pl-5 pr-3">
            <div class="min-w-0">{{ $header }}</div>
            <button type="button" data-autofocus @click="open = false" aria-label="Tutup menu"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-stone-800 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-copper-500">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">{{ $slot }}</div>

        @isset($footer)
            <div class="shrink-0 border-t border-stone-200 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">{{ $footer }}</div>
        @endisset
    </div>
</div>
