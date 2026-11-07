{{--
    Garis perjalanan berbentuk S. Desktop: jalur SVG yang berkelok dengan kartu di sisi berlawanan tiap titik.
    Mobile: daftar vertikal biasa. Isi diatur di Admin > Milestone.
--}}
@props(['items', 'compact' => false])
@php
    $items = collect($items)->values();
    $n = $items->count();
    $rowH = $compact ? 200 : 240;
    $pad = 80;
    $H = $pad * 2 + max(0, $n - 1) * $rowH;
    $x = fn ($i) => $i % 2 === 0 ? 300 : 700;
    $y = fn ($i) => $pad + $i * $rowH;
    $d = 'M ' . $x(0) . ' 0 L ' . $x(0) . ' ' . $y(0);
    for ($i = 0; $i < $n - 1; $i++) {
        $k = $rowH * 0.6;
        $d .= " C {$x($i)} " . ($y($i) + $k) . " {$x($i + 1)} " . ($y($i + 1) - $k) . " {$x($i + 1)} {$y($i + 1)}";
    }
    $d .= ' L ' . $x(max(0, $n - 1)) . ' ' . $H;
@endphp

@if($n)
    <div class="relative mx-auto hidden w-full max-w-5xl lg:block" style="aspect-ratio: 1000 / {{ $H }};" data-draw>
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 1000 {{ $H }}" fill="none" aria-hidden="true">
            <path d="{{ $d }}" stroke="#e8dfd5" stroke-width="10" stroke-linecap="round" pathLength="1"/>
            <path class="ms-path" d="{{ $d }}" stroke="#a8643a" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="1" pathLength="1"/>
        </svg>

        <ol class="contents">
            @foreach($items as $i => $m)
                @php $left = $i % 2 === 0; $top = $y($i) / $H * 100; @endphp
                <li class="contents">
                    <span class="absolute z-10 flex h-9 w-9 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border-[3px] border-orange-500 bg-white shadow-sm" style="left: {{ $x($i) / 10 }}%; top: {{ $top }}%" data-reveal>
                        <span class="h-3 w-3 rounded-full bg-orange-500"></span>
                    </span>
                    <div class="absolute -translate-y-1/2 {{ $left ? 'text-left' : 'text-right' }}"
                         style="top: {{ $top }}%; {{ $left ? 'left: ' . (($x($i) + 70) / 10) . '%' : 'right: ' . ((1000 - $x($i) + 70) / 10) . '%' }}; width: {{ $compact ? 40 : 46 }}%; --d: {{ 120 + $i * 80 }}ms"
                         data-reveal>
                        <p class="ms-year">{{ $m->year }}</p>
                        <h3 class="ms-title">{{ $m->title }}</h3>
                        @if($m->description)<p class="ms-desc">{{ $compact ? \Illuminate\Support\Str::limit($m->description, 100) : $m->description }}</p>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <ol class="relative ml-3 space-y-9 border-l-2 border-stone-300 pl-8 lg:hidden">
        @foreach($items as $i => $m)
            <li class="relative" data-reveal style="--d: {{ $i * 70 }}ms">
                <span class="absolute -left-[45px] top-1 flex h-6 w-6 items-center justify-center rounded-full border-[3px] border-orange-500 bg-white"><span class="h-2 w-2 rounded-full bg-orange-500"></span></span>
                <p class="ms-year">{{ $m->year }}</p>
                <h3 class="ms-title">{{ $m->title }}</h3>
                @if($m->description)<p class="ms-desc">{{ $m->description }}</p>@endif
            </li>
        @endforeach
    </ol>
@endif
