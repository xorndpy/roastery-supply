@props(['user' => null, 'size' => 32, 'ring' => false])
@php
    $user = $user ?? auth()->user();
    $url = $user?->avatar_url;
    $px = (int) $size;
    $style = "width:{$px}px;height:{$px}px;flex:none;border-radius:999px;" . ($ring ? 'box-shadow:0 0 0 3px #fff,0 2px 8px rgb(0 0 0 / .18);' : '');
@endphp
@if($url)
    <img src="{{ $url }}" alt="" loading="lazy" referrerpolicy="no-referrer" style="{{ $style }}object-fit:cover;background:#efe6dc" {{ $attributes }}>
@else
    <span aria-hidden="true" style="{{ $style }}display:inline-flex;align-items:center;justify-content:center;background:#4a2f21;color:#fbeadb;font-weight:600;letter-spacing:.03em;font-size:{{ max(10, (int) round($px * .36)) }}px" {{ $attributes }}>{{ $user?->initials ?? 'U' }}</span>
@endif
