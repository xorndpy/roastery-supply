{{-- Kerangka halaman galat. Sengaja mandiri (CSS inline, tanpa database/Vite) supaya tetap tampil saat sistem bermasalah. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') ({{ $__env->yieldContent('code') }}) | {{ config('app.name') }}</title>
    <style>
        :root { --ink:#27272a; --muted:#57534e; --bg:#faf7f2; --coffee:#4a2f21; --copper:#a8643a; --line:#e7ddd2; }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; -webkit-font-smoothing: antialiased; }
        .wrap { min-height: 100%; display: flex; flex-direction: column; padding: 24px; }
        .brand { font-family: Georgia, "Times New Roman", serif; font-weight: 700; font-size: 1.25rem; color: var(--coffee); text-decoration: none; }
        main { flex: 1; display: flex; flex-direction: column; justify-content: center; max-width: 560px; width: 100%; margin: 0 auto; padding: 32px 0; }
        .ghost { font-family: Georgia, "Times New Roman", serif; font-weight: 700; font-size: clamp(5rem, 22vw, 9rem); line-height: 1; color: var(--copper); opacity: .9; margin: 0 0 4px; letter-spacing: -.02em; }
        .eyebrow { font-size: .75rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--copper); margin: 0 0 8px; }
        h1 { font-family: Georgia, "Times New Roman", serif; font-size: clamp(1.6rem, 5vw, 2.1rem); line-height: 1.2; margin: 0 0 12px; color: var(--ink); }
        p.msg { margin: 0 0 28px; color: var(--muted); font-size: 1.0625rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 22px; border-radius: 10px; font: inherit; font-weight: 600; font-size: .9375rem; text-decoration: none; cursor: pointer; border: 2px solid var(--coffee); }
        .btn-primary { background: var(--coffee); color: #fff; }
        .btn-primary:hover { background: #3a2318; border-color: #3a2318; }
        .btn-ghost { background: transparent; color: var(--coffee); }
        .btn-ghost:hover { background: #f1e8de; }
        .btn:focus-visible, .brand:focus-visible, .help a:focus-visible { outline: 3px solid var(--copper); outline-offset: 3px; }
        .help { margin-top: 36px; padding-top: 20px; border-top: 1px solid var(--line); font-size: .875rem; color: var(--muted); }
        .help a { color: var(--coffee); font-weight: 600; }
        footer { max-width: 560px; width: 100%; margin: 0 auto; font-size: .75rem; color: var(--muted); }
        @media (prefers-reduced-motion: no-preference) { .btn { transition: background-color .15s ease; } }
    </style>
</head>
<body>
<div class="wrap">
    <header><a class="brand" href="{{ url('/') }}">{{ config('app.name') }}</a></header>

    <main id="main">
        <p class="ghost" aria-hidden="true">@yield('code')</p>
        <p class="eyebrow">Kode galat @yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="msg">@yield('message')</p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Kembali ke beranda</a>
            @yield('secondary')
        </div>

        <p class="help">Butuh bantuan? <a href="{{ url('/kontak') }}">Hubungi kami</a> dan sebutkan kode {{ $__env->yieldContent('code') }}.</p>
    </main>

    <footer>&copy; {{ date('Y') }} {{ config('app.name') }}</footer>
</div>
</body>
</html>
