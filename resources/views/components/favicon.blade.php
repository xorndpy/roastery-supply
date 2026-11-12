@php
    // Favicon dari Pengaturan (Umum > Favicon); kosong = ikon bawaan di folder public/.
    $favicon = \App\Models\Setting::get('site_favicon');
@endphp
@if($favicon)
    <link rel="icon" href="{{ media_url($favicon) }}">
    <link rel="apple-touch-icon" href="{{ media_url($favicon) }}">
@else
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@endif
