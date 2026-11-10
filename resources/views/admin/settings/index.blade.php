@extends('layouts.admin')

@section('title', 'Pengaturan')

@section('content')
@php
    // Tab -> [judul, keterangan, grup di config/cms.php]. Semua tab berada dalam SATU form, jadi satu tombol simpan cukup.
    $tabs = [
        'profil'   => ['Profil Usaha', 'Nama, logo, kontak, media sosial', ['Umum', 'Kontak', 'Media Sosial']],
        'bayar'    => ['Pembayaran & Pengiriman', 'Midtrans, transfer manual, ongkir', ['Rekening Pembayaran', 'Pengiriman']],
        'tampilan' => ['Tampilan Toko', 'Kartu produk dan teks beranda', ['Tampilan Produk', 'Beranda']],
        'header'   => ['Header Halaman', 'Foto latar tiap halaman', ['Header Halaman']],
        'tentang'  => ['Halaman Tentang', 'Profil usaha dan foto', ['Halaman Tentang']],
        'seo'      => ['SEO', 'Tampil di hasil pencarian', ['SEO']],
    ];
    $groupTitles = ['Rekening Pembayaran' => 'Transfer manual (cadangan)', 'Pengiriman' => 'Gratis ongkir'];

    $groupOf = [];
    foreach ($groups as $g => $fields) {
        foreach ($fields as $k => $_) { $groupOf[$k] = $g; }
    }
    $tabOfGroup = [];
    foreach ($tabs as $tk => [$label, $desc, $gs]) {
        foreach ($gs as $g) { $tabOfGroup[$g] = $tk; }
    }

    $initial = old('tab', request('tab', 'profil'));
    if ($errors->any()) {
        $initial = $tabOfGroup[$groupOf[array_key_first($errors->messages())] ?? ''] ?? $initial; // buka tab yang berisi error
    }
    if (!isset($tabs[$initial])) { $initial = 'profil'; }
@endphp

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Pengaturan Situs</h1>
    <p class="mt-1 text-sm text-stone-500">Semua bagian disimpan sekaligus dengan satu tombol di bawah.</p>
</div>

@if ($errors->any())
    <div class="mb-4 max-w-4xl rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
        <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-6xl lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start lg:gap-8"
      x-data="{ tab: @js($initial), dirty: false }" @input="dirty = true" @change="dirty = true">
    @csrf @method('PUT')
    <input type="hidden" name="tab" :value="tab">

    {{-- Navigasi bagian --}}
    <nav class="mb-5 lg:sticky lg:top-24 lg:mb-0" aria-label="Bagian pengaturan">
        <div class="flex gap-1.5 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible lg:pb-0" role="tablist">
            @foreach($tabs as $tk => [$label, $desc, $gs])
                <button type="button" role="tab" @click="tab = '{{ $tk }}'" :aria-selected="(tab === '{{ $tk }}').toString()"
                        :class="tab === '{{ $tk }}' ? 'border-stone-200 bg-white text-stone-900 shadow-sm' : 'border-transparent text-stone-600 hover:bg-stone-100'"
                        class="shrink-0 rounded-lg border px-3.5 py-2.5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                    <span class="block whitespace-nowrap text-sm font-medium">{{ $label }}</span>
                    <span class="hidden text-xs text-stone-500 lg:block">{{ $desc }}</span>
                </button>
            @endforeach
        </div>
    </nav>

    <div class="min-w-0 max-w-3xl">
        @foreach($tabs as $tk => [$label, $desc, $gs])
            <div x-show="tab === '{{ $tk }}'" @if($tk !== $initial) x-cloak @endif class="space-y-5" role="tabpanel" aria-label="{{ $label }}">

                @if($tk === 'bayar')
                    {{-- Status Midtrans (hanya baca; kunci API diatur di server) --}}
                    <section class="rounded-xl border border-stone-200 bg-white p-6" aria-labelledby="mid-title">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h2 id="mid-title" class="!mb-0 !border-0 !pb-0 font-bold">Pembayaran online (Midtrans)</h2>
                                <p class="mt-1 text-sm text-stone-500">E-wallet, virtual account, QRIS, dan kartu lewat Midtrans. Status pembayaran dikonfirmasi otomatis.</p>
                            </div>
                            @if($midtrans['configured'])
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800"><i class="h-1.5 w-1.5 rounded-full bg-green-600"></i>Aktif</span>
                            @else
                                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-800"><i class="h-1.5 w-1.5 rounded-full bg-red-600"></i>Belum dikonfigurasi</span>
                            @endif
                        </div>

                        @if($midtrans['configured'] && !$midtrans['production'])
                            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="note">Mode uji (Sandbox): pembayaran tidak benar-benar masuk. Ubah ke Produksi saat toko siap menerima uang sungguhan.</p>
                        @endif

                        <dl class="mt-5 grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs text-stone-500">Mode</dt><dd class="mt-0.5 font-medium">{{ $midtrans['production'] ? 'Produksi' : 'Sandbox (uji coba)' }}</dd></div>
                            <div><dt class="text-xs text-stone-500">Client key</dt><dd class="mt-0.5 font-mono text-[13px]">{{ $midtrans['client_key'] ?? 'Belum diisi' }}</dd></div>
                            <div><dt class="text-xs text-stone-500">Batas waktu bayar</dt><dd class="mt-0.5 font-medium">{{ rtrim(rtrim(number_format($midtrans['expiry_hours'], 1, ',', '.'), '0'), ',') }} jam</dd></div>
                            <div><dt class="text-xs text-stone-500">Konfirmasi pembayaran</dt><dd class="mt-0.5 font-medium">{{ $midtrans['inline'] ? 'Langsung saat notifikasi diterima' : 'Lewat antrean (worker)' }}</dd></div>
                        </dl>

                        <div class="mt-5" x-data="{ copied: false }">
                            <label for="mid-webhook" class="mb-1.5 block text-sm font-medium text-stone-800">URL notifikasi pembayaran</label>
                            <div class="flex gap-2">
                                <input id="mid-webhook" type="text" readonly value="{{ $midtrans['webhook'] }}" class="w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 font-mono text-[13px] text-stone-700">
                                <button type="button" @click="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('mid-webhook').value); copied = true; setTimeout(() => copied = false, 2000)"
                                        class="shrink-0 rounded-lg border border-stone-300 bg-white px-3 text-sm font-medium text-stone-700 hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                                    <span x-text="copied ? 'Tersalin' : 'Salin'" aria-live="polite">Salin</span>
                                </button>
                            </div>
                            <p class="mt-1.5 text-xs leading-relaxed text-stone-500">Tempel di dashboard Midtrans (menu Settings &gt; Configuration &gt; Payment Notification URL).</p>
                        </div>

                        <p class="mt-5 border-t border-stone-100 pt-4 text-xs leading-relaxed text-stone-500">Kunci API sengaja tidak diubah dari halaman ini supaya tidak bocor lewat panel. Atur di server (Railway &gt; Variables): <span class="font-mono">MIDTRANS_SERVER_KEY</span>, <span class="font-mono">MIDTRANS_CLIENT_KEY</span>, <span class="font-mono">MIDTRANS_IS_PRODUCTION</span>.</p>
                    </section>
                @endif

                @if($tk === 'tampilan')
                    <a href="{{ route('admin.hero.index') }}" class="flex items-center justify-between gap-4 rounded-xl border border-orange-200 bg-orange-50 p-5 transition hover:bg-orange-100/60">
                        <div>
                            <p class="font-bold text-stone-900">Foto latar hero beranda (slideshow)</p>
                            <p class="mt-0.5 text-sm text-stone-600">Unggah banyak foto sekaligus, atur urutannya, dan aktif/nonaktifkan.</p>
                        </div>
                        <span class="shrink-0 text-sm font-medium text-orange-700 underline underline-offset-4">Kelola</span>
                    </a>
                @endif

                @if($tk === 'header')
                    <a href="{{ route('admin.banners.index') }}" class="flex items-center justify-between gap-4 rounded-xl border border-orange-200 bg-orange-50 p-5 transition hover:bg-orange-100/60">
                        <div>
                            <p class="font-bold text-stone-900">Slider beranda produk (3–5 foto)</p>
                            <p class="mt-0.5 text-sm text-stone-600">Foto terbaru dan promo di paling atas halaman Produk. Atur di Banner &amp; Promo, pilih tampil di "Katalog".</p>
                        </div>
                        <span class="shrink-0 text-sm font-medium text-orange-700 underline underline-offset-4">Kelola</span>
                    </a>
                    <p class="rounded-xl border border-stone-200 bg-white p-4 text-sm leading-relaxed text-stone-600">Setiap halaman punya fotonya sendiri, jadi tidak lagi seragam. Halaman tanpa foto tampil dengan latar polos. Banner tiap kategori produk diatur di <a href="{{ route('admin.categories.index') }}" class="font-medium text-orange-700 underline">Kategori</a>.</p>
                @endif

                @foreach($gs as $group)
                    @continue(!isset($groups[$group]))
                    @php $title = $groupTitles[$group] ?? $group; $isFallback = $group === 'Rekening Pembayaran'; @endphp

                    @if($isFallback)
                        <details class="group rounded-xl border border-stone-200 bg-white" @if($errors->any() && collect($groups[$group])->keys()->intersect(array_keys($errors->messages()))->isNotEmpty()) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-6 [&::-webkit-details-marker]:hidden">
                                <span>
                                    <span class="block font-bold">{{ $title }}</span>
                                    <span class="mt-0.5 block text-sm font-normal text-stone-500">Hanya dipakai bila pelanggan memilih transfer manual atau pembayaran online gagal.</span>
                                </span>
                                <svg class="chev h-4 w-4 shrink-0 text-stone-500 transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                            </summary>
                            <div class="grid gap-5 border-t border-stone-100 p-6 md:grid-cols-2">
                                @foreach($groups[$group] as $key => $def)
                                    @php $val = old($key, $values[$key] ?? ($def['default'] ?? '')); @endphp
                                    @include('admin.settings._field', ['key' => $key, 'def' => $def, 'val' => $val, 'values' => $values, 'group' => $group])
                                @endforeach
                            </div>
                        </details>
                    @else
                        <section class="rounded-xl border border-stone-200 bg-white p-6">
                            <h2 class="!mb-4 font-bold">{{ $title }}</h2>
                            <div class="grid gap-5 md:grid-cols-2">
                                @foreach($groups[$group] as $key => $def)
                                    @php $val = old($key, $values[$key] ?? ($def['default'] ?? '')); @endphp
                                    @include('admin.settings._field', ['key' => $key, 'def' => $def, 'val' => $val, 'values' => $values, 'group' => $group])
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            </div>
        @endforeach

        @can('edit_settings')
            <div class="sticky bottom-4 z-10 mt-6 flex items-center justify-between gap-4 rounded-xl border border-stone-200 bg-white/95 px-5 py-3 shadow-lg backdrop-blur">
                <p class="text-sm" :class="dirty ? 'text-amber-700' : 'text-stone-500'" x-text="dirty ? 'Ada perubahan yang belum disimpan' : 'Belum ada perubahan'" role="status"></p>
                <button class="rounded-lg bg-orange-500 px-6 py-2 font-medium text-white hover:bg-orange-600">Simpan Pengaturan</button>
            </div>
        @endcan
    </div>
</form>

@endsection
