@extends('layouts.admin')

@section('title', 'Pengaturan')

@section('content')
@php
    // Tab -> [judul, grup di config/cms.php]. Semua tab berada dalam SATU form, jadi satu tombol simpan cukup.
    $tabs = [
        'profil'   => ['Profil Usaha', ['Umum', 'Kontak', 'Media Sosial']],
        'bayar'    => ['Pembayaran & Pengiriman', ['Rekening Pembayaran', 'Pengiriman']],
        'tampilan' => ['Tampilan', ['Tampilan']],
        'tentang'  => ['Halaman Tentang', ['Halaman Tentang']],
        'seo'      => ['SEO', ['SEO']],
    ];

    $groupOf = [];
    foreach ($groups as $g => $fields) {
        foreach ($fields as $k => $_) { $groupOf[$k] = $g; }
    }
    $tabOfGroup = [];
    foreach ($tabs as $tk => [$label, $gs]) {
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
    <p class="text-sm text-stone-500 mt-1">Nama, kontak, rekening, tampilan, dan halaman Tentang. Semua tab disimpan sekaligus.</p>
</div>

@if ($errors->any())
    <div class="mb-4 max-w-3xl p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-3xl"
      x-data="{ tab: @js($initial), dirty: false }" @input="dirty = true" @change="dirty = true">
    @csrf @method('PUT')
    <input type="hidden" name="tab" :value="tab">

    {{-- Tab --}}
    <div class="mb-5 flex gap-1 overflow-x-auto border-b border-stone-200" role="tablist">
        @foreach($tabs as $tk => [$label, $gs])
            <button type="button" role="tab" @click="tab = '{{ $tk }}'" :aria-selected="tab === '{{ $tk }}'"
                    :class="tab === '{{ $tk }}' ? 'border-orange-500 text-stone-900' : 'border-transparent text-stone-500 hover:text-stone-800'"
                    class="-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition">{{ $label }}</button>
        @endforeach
    </div>

    @foreach($tabs as $tk => [$label, $gs])
        <div x-show="tab === '{{ $tk }}'" @if($tk !== $initial) x-cloak @endif class="space-y-6" role="tabpanel">

            @if($tk === 'tampilan')
                <a href="{{ route('admin.hero.index') }}" class="flex items-center justify-between gap-4 rounded-lg border border-orange-200 bg-orange-50 p-5 hover:bg-orange-100/60 transition">
                    <div>
                        <p class="font-bold text-stone-900">Foto latar hero beranda (slideshow)</p>
                        <p class="text-sm text-stone-600 mt-0.5">Unggah banyak foto sekaligus, atur urutannya, dan aktif/nonaktifkan.</p>
                    </div>
                    <span class="shrink-0 text-sm font-medium text-orange-700">Kelola &rarr;</span>
                </a>
            @endif

            @foreach($gs as $group)
                @continue(!isset($groups[$group]))
                <section class="bg-white p-6 rounded-lg border border-stone-200">
                    <h2 class="font-bold mb-4">{{ $group }}</h2>
                    <div class="space-y-4">
                        @foreach($groups[$group] as $key => $def)
                            @php $val = old($key, $values[$key] ?? ($def['default'] ?? '')); @endphp
                            <div>
                                <label class="block text-sm font-medium mb-1.5" for="f-{{ $key }}">{{ $def['label'] }}</label>

                                @if($def['type'] === 'textarea')
                                    <textarea id="f-{{ $key }}" name="{{ $key }}" rows="{{ $def['rows'] ?? 3 }}" maxlength="{{ $def['max'] ?? 500 }}"
                                              class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ $val }}</textarea>
                                @elseif($def['type'] === 'select')
                                    <select id="f-{{ $key }}" name="{{ $key }}" class="w-full border border-stone-300 rounded-lg px-3 py-2 bg-white">
                                        @foreach($def['options'] as $optValue => $optLabel)
                                            <option value="{{ $optValue }}" @selected((string) $val === (string) $optValue)>{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif($def['type'] === 'image')
                                    <div x-data="{ src: null }">
                                        @if(!empty($values[$key]))
                                            <img x-show="!src" src="{{ media_url($values[$key]) }}" alt="" class="h-20 mb-2 rounded border border-stone-200">
                                            <label x-show="!src" class="flex items-center gap-2 text-xs text-stone-500 mb-2">
                                                <input type="checkbox" name="remove_{{ $key }}" value="1"> Hapus gambar ini
                                            </label>
                                        @endif
                                        <img x-show="src" x-cloak :src="src" alt="" class="h-20 mb-2 rounded border border-orange-300">
                                        <input id="f-{{ $key }}" type="file" name="{{ $key }}" accept="image/jpeg,image/png,image/webp"
                                               @change="src = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                                               class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
                                    </div>
                                @else
                                    <input id="f-{{ $key }}" type="{{ in_array($def['type'], ['url', 'email', 'number']) ? $def['type'] : 'text' }}" @if($def['type'] === 'number') min="0" step="1000" @endif
                                           name="{{ $key }}" value="{{ $val }}" maxlength="{{ $def['max'] ?? 255 }}"
                                           class="w-full border border-stone-300 rounded-lg px-3 py-2">
                                @endif

                                @if(!empty($def['help']))<p class="text-xs text-stone-500 mt-1">{{ $def['help'] }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endforeach

    @can('edit_settings')
        <div class="sticky bottom-4 z-10 mt-6 flex items-center justify-between gap-4 rounded-xl border border-stone-200 bg-white/95 px-5 py-3 shadow-lg backdrop-blur">
            <p class="text-sm" :class="dirty ? 'text-amber-700' : 'text-stone-500'" x-text="dirty ? 'Ada perubahan yang belum disimpan' : 'Belum ada perubahan'"></p>
            <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-lg font-medium">Simpan Pengaturan</button>
        </div>
    @endcan
</form>

@endsection
