@props(['title', 'toc' => [], 'updated', 'lead' => null])
<x-page-header :title="$title" :crumbs="[['Beranda', route('home')], [$title]]" :plain="true" />

{{-- Gaya dibuat mandiri (bukan utility Tailwind) supaya tampil benar tanpa perlu build ulang aset. --}}
<style>
    .legal-grid { display: grid; gap: 2rem; }
    @media (min-width: 900px) { .legal-grid { grid-template-columns: 240px minmax(0, 1fr); gap: 3.5rem; } .legal-toc { position: sticky; top: 96px; align-self: start; } }
    .legal-toc { font-size: .875rem; }
    .legal-toc p { margin: 0 0 .75rem; font-weight: 700; font-size: .75rem; letter-spacing: .12em; text-transform: uppercase; color: #292524; }
    .legal-toc ol { margin: 0; padding: 0; list-style: none; border-left: 2px solid #e7e5e4; }
    .legal-toc a { display: block; padding: .4rem 0 .4rem .9rem; margin-left: -2px; border-left: 2px solid transparent; color: #57534e; text-decoration: none; }
    .legal-toc a:hover { color: #a8643a; border-left-color: #a8643a; }
    .legal-toc a:focus-visible, .legal a:focus-visible { outline: 3px solid #a8643a; outline-offset: 2px; border-radius: 2px; }
    .legal { max-width: 72ch; color: #44403c; line-height: 1.75; font-size: 1rem; }
    .legal .legal-meta { margin: 0 0 1rem; font-size: .8125rem; color: #57534e; }
    .legal .legal-lead { margin: 0 0 1.5rem; font-size: 1.0625rem; color: #292524; }
    .legal h2 { scroll-margin-top: 96px; margin: 2.5rem 0 .75rem; font-size: 1.3rem; line-height: 1.3; color: #1c1917; }
    .legal h3 { margin: 1.5rem 0 .5rem; font-size: 1rem; color: #1c1917; }
    .legal p { margin: 0 0 1rem; }
    .legal ul, .legal ol { margin: 0 0 1rem; padding-left: 1.4rem; }
    .legal li { margin: .35rem 0; }
    .legal a { color: #8f5230; text-decoration: underline; text-underline-offset: 2px; }
    .legal strong { color: #1c1917; }
    .legal .legal-note { margin: 1.25rem 0; padding: .9rem 1.1rem; background: #fdf3e7; border-left: 4px solid #a8643a; border-radius: 0 8px 8px 0; color: #44403c; }
    .legal .legal-contact { margin-top: 3rem; padding: 1.25rem 1.5rem; border: 1px solid #e7e5e4; border-radius: 12px; background: #fff; }
    .legal .legal-contact h2 { margin-top: 0; }
    .legal .legal-contact p { margin: .25rem 0; }
</style>

<div class="container-x py-10 sm:py-14">
    <div class="legal-grid">
        <nav class="legal-toc" aria-label="Daftar isi halaman">
            <p>Daftar isi</p>
            <ol>
                @foreach($toc as $id => $label)
                    <li><a href="#{{ $id }}">{{ $label }}</a></li>
                @endforeach
                <li><a href="#kontak">Hubungi kami</a></li>
            </ol>
        </nav>

        <article class="legal">
            <p class="legal-meta">Terakhir diperbarui: {{ $updated }}</p>
            @if($lead)<p class="legal-lead">{{ $lead }}</p>@endif

            {{ $slot }}

            <section class="legal-contact" aria-labelledby="kontak">
                <h2 id="kontak">Hubungi kami</h2>
                <p><strong>{{ $site['site_name'] }}</strong></p>
                @if(!empty($site['contact_address']))<p>{{ $site['contact_address'] }}</p>@endif
                @if(!empty($site['contact_email']))<p>Email: <a href="mailto:{{ $site['contact_email'] }}">{{ $site['contact_email'] }}</a></p>@endif
                @if(!empty($site['contact_phone']))<p>Telepon / WhatsApp: {{ $site['contact_phone'] }}</p>@endif
                @if(!empty($site['contact_hours']))<p>Jam operasional: {{ $site['contact_hours'] }}</p>@endif
                <p>Atau kirim pesan lewat <a href="{{ route('contact.index') }}">formulir kontak</a>.</p>
            </section>
        </article>
    </div>
</div>
