@extends('layouts.app')

@section('title', 'Hubungi Kami')

@section('content')
@php
    $wa = !empty($site['contact_whatsapp']) ? preg_replace('/\D/', '', $site['contact_whatsapp']) : null;
    $cards = array_values(array_filter([
        $wa ? ['whatsapp', 'WhatsApp', 'Respon cepat, chat langsung', 'https://wa.me/' . $wa] : null,
        !empty($site['contact_email']) ? ['mail', 'Email', $site['contact_email'], 'mailto:' . $site['contact_email']] : null,
        !empty($site['contact_phone']) ? ['phone', 'Telepon', $site['contact_phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $site['contact_phone'])] : null,
        !empty($site['contact_address']) ? ['pin', 'Kantor Pusat', $site['contact_address'], null] : null,
        !empty($site['contact_hours']) ? ['clock', 'Jam Operasional', $site['contact_hours'], null] : null,
    ]));
    $mapData = $branches->map(function ($b) {
        $has = $b->latitude !== null && $b->longitude !== null;
        return [
            'name'    => $b->name,
            'address' => $b->address,
            'phone'   => $b->phone ? preg_replace('/[^0-9+]/', '', $b->phone) : null,
            'lat'     => $has ? (float) $b->latitude : null,
            'lng'     => $has ? (float) $b->longitude : null,
            'url'     => $b->maps_url ?: ($has
                ? 'https://www.google.com/maps?q=' . (float) $b->latitude . ',' . (float) $b->longitude
                : 'https://www.google.com/maps/search/?api=1&query=' . urlencode((string) $b->address)),
        ];
    })->values();
@endphp

<x-page-header page="kontak" :title="'Hubungi ' . $site['site_name']" subtitle="Ada pertanyaan seputar produk, harga, atau ingin konsultasi kebutuhan kedai Anda? Tim kami siap membantu." eyebrow="Kontak" :crumbs="[['Beranda', route('home')], ['Hubungi ' . $site['site_name']]]" />

<div class="container-x grid items-start gap-8 py-12 lg:grid-cols-[380px_1fr]">

    {{-- Kartu kontak --}}
    <div class="space-y-3.5">
        @forelse($cards as [$icon, $title, $text, $href])
            @php $tag = $href ? 'a' : 'div'; @endphp
            <{{ $tag }} @if($href) href="{{ $href }}" @if(str_starts_with($href, 'http')) target="_blank" rel="noopener noreferrer" @endif @endif
                class="flex items-start gap-4 rounded-xl border border-stone-200 bg-white p-4 shadow-sm {{ $href ? 'transition hover:-translate-y-0.5 hover:border-orange-500 hover:shadow-md' : '' }}">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <x-ui-icon :name="$icon" class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-stone-900">{{ $title }}</span>
                    <span class="mt-0.5 block whitespace-pre-line break-words text-sm leading-relaxed text-stone-500">{{ $text }}</span>
                </span>
            </{{ $tag }}>
        @empty
            <div class="rounded-xl border border-dashed border-stone-300 bg-white p-5 text-sm text-stone-500">
                Data kontak belum diisi. Lengkapi di Admin &rarr; Pengaturan.
            </div>
        @endforelse
    </div>

    {{-- Form --}}
    <div class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="eyebrow">Formulir</div>
        <h2 class="heading mt-1 text-2xl">Kirim Pesan</h2>

        @if(session('success'))
            <div class="mt-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('contact.send') }}" class="mt-6 space-y-5">
            @csrf

            {{-- Honeypot anti-spam --}}
            <div style="position:absolute;left:-10000px" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="c-name" class="mb-1.5 block text-sm font-medium">Nama Anda</label>
                    <input id="c-name" type="text" name="name" value="{{ old('name') }}" required placeholder="Masukkan nama Anda" class="field">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="c-email" class="mb-1.5 block text-sm font-medium">Email Anda</label>
                    <input id="c-email" type="email" name="email" value="{{ old('email') }}" required placeholder="Masukkan email Anda" class="field">
                    @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="c-phone" class="mb-1.5 block text-sm font-medium">Nomor Telepon <span class="font-normal text-stone-400">(opsional)</span></label>
                    <input id="c-phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" class="field">
                </div>
                <div>
                    <label for="c-subject" class="mb-1.5 block text-sm font-medium">Subjek</label>
                    <input id="c-subject" type="text" name="subject" value="{{ old('subject') }}" required placeholder="Contoh: Tanya mesin espresso" class="field">
                    @error('subject') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="c-message" class="mb-1.5 block text-sm font-medium">Pesan Anda</label>
                <textarea id="c-message" name="message" rows="5" required placeholder="Tuliskan pertanyaan atau kebutuhan Anda…" class="field-area">{{ old('message') }}</textarea>
                @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn btn-primary w-full">Kirim Pesan</button>
        </form>
    </div>
</div>

{{-- Lokasi / cabang --}}
@if($branches->isNotEmpty())
    <section class="container-x pb-6"
        x-data='{
            list: @json($mapData), i: 0,
            get b() { return this.list[this.i]; },
            get src() {
                const b = this.b;
                return "https://maps.google.com/maps?q=" + (b.lat !== null ? b.lat + "," + b.lng : encodeURIComponent(b.address)) + "&z=16&output=embed";
            }
        }'>
        <div class="eyebrow">Lokasi Kami</div>
        <h2 class="heading mt-1 text-3xl">Cabang {{ $site['site_name'] }}</h2>
        <p class="mt-1 text-sm text-stone-500">Kunjungi salah satu cabang terdekat, atau langsung hubungi kami lewat WhatsApp.</p>

        <div class="relative mt-6 overflow-hidden rounded-2xl border border-stone-200 bg-stone-100 shadow-sm">
            <iframe :src="src" title="Peta cabang" class="h-[420px] w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>

            <div class="max-h-[380px] overflow-y-auto border-t border-stone-200 bg-white p-4 sm:absolute sm:left-4 sm:top-4 sm:w-80 sm:rounded-xl sm:border sm:shadow-lg">
                <div class="mb-3 flex flex-wrap gap-1.5" x-show="list.length > 1">
                    <template x-for="(item, n) in list" :key="n">
                        <button type="button" @click="i = n"
                                class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition"
                                :class="i === n ? 'bg-orange-500 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200'" x-text="n + 1"></button>
                    </template>
                </div>
                <div class="text-sm font-semibold uppercase tracking-wide" x-text="b.name"></div>
                <p class="mt-1.5 whitespace-pre-line text-[13px] leading-relaxed text-stone-500" x-text="b.address"></p>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[13px] font-medium">
                    <a :href="b.url" target="_blank" rel="noopener noreferrer" class="text-orange-600 hover:underline">Buka di Maps →</a>
                    <template x-if="b.phone"><a :href="'tel:' + b.phone" class="text-orange-600 hover:underline">Hubungi cabang ini →</a></template>
                </div>
            </div>
        </div>
    </section>
@endif
@endsection
