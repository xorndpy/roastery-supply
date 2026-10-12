@props(['title', 'subtitle' => null, 'eyebrow' => null, 'crumbs' => [], 'bg' => null])
@php
    // Gambar: parameter bg, lalu pengaturan "header_bg" (Admin > Pengaturan > Tampilan).
    $img = $bg ?: ($site['header_bg'] ?? null);
@endphp

<div class="relative overflow-hidden border-b {{ $img ? 'border-transparent bg-[#24150e]' : 'border-stone-200 bg-stone-100' }}">
    @if($img)
        <img src="{{ asset('storage/' . $img) }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-r from-[#24150e]/90 via-[#24150e]/65 to-[#24150e]/25"></div>
    @else
        <div class="pointer-events-none absolute -right-20 -top-28 h-80 w-80 rounded-full bg-orange-200/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-orange-100/70 blur-3xl"></div>
    @endif

    <div class="container-x relative {{ $img ? 'py-14 sm:py-20' : 'py-10 sm:py-12' }} {{ $img ? 'text-white' : 'text-stone-900' }}">
        @if(count($crumbs))
            <nav class="mb-3 text-[13px] {{ $img ? 'text-white/70' : 'text-stone-500' }}" aria-label="Breadcrumb">
                @foreach($crumbs as $i => $crumb)
                    @if($i > 0)<span class="mx-1">/</span>@endif
                    @if(!empty($crumb[1]))
                        <a href="{{ $crumb[1] }}" class="{{ $img ? 'hover:text-white' : 'hover:text-stone-900' }}">{{ $crumb[0] }}</a>
                    @else
                        <span>{{ $crumb[0] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif

        @if($eyebrow)<div class="eyebrow {{ $img ? '!text-orange-300' : '' }}">{{ $eyebrow }}</div>@endif
        <h1 class="heading mt-1 text-4xl sm:text-5xl">{{ $title }}</h1>
        @if($subtitle)<p class="mt-3 max-w-xl {{ $img ? 'text-white/80' : 'text-stone-600' }}">{{ $subtitle }}</p>@endif
    </div>
</div>
