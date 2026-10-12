{{--
    Field form untuk halaman auth.
    Label selalu terlihat (tidak hanya placeholder), ikon dekoratif, error terhubung lewat aria,
    dan untuk password: tombol lihat/sembunyikan + peringatan Caps Lock.

    <x-auth-input name="password" label="Password" icon="lock" type="password" toggle :invalid="$errors->has('password')">
        <x-slot:aside>...tautan kecil di sisi kanan label...</x-slot:aside>
    </x-auth-input>

    Catatan: atribut Alpine di komponen ini memakai x-model / x-ref (tanpa tanda titik dua).
--}}
@props([
    'name',
    'label',
    'icon' => null,
    'type' => 'text',
    'toggle' => false,
    'invalid' => false,
    'messages' => [],
    'hint' => null,
])
@php
    $id = $attributes->get('id', $name);
    $describedBy = trim(($hint ? "$id-hint " : '') . (!empty($messages) ? "$id-error" : ''));
    $icons = [
        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M4 7.5l8 6 8-6"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c.8-3.6 3.5-5.5 7-5.5s6.2 1.9 7 5.5"/>',
    ];
@endphp

<div x-data="{ show: false, caps: false }">
    <div class="flex items-baseline justify-between gap-3">
        <label for="{{ $id }}" class="block text-sm font-medium text-stone-700">{{ $label }}</label>
        {{ $aside ?? '' }}
    </div>

    <div class="auth-field mt-1.5">
        @if ($icon && isset($icons[$icon]))
            <svg class="auth-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>
        @endif

        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
               @if ($toggle) :type="show ? 'text' : 'password'" @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')" @blur="caps = false" @endif
               @if ($invalid) aria-invalid="true" @endif
               @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
               {{ $attributes->except('id')->merge(['class' => 'auth-input' . ($toggle ? ' pr-12' : '')]) }}>

        @if ($toggle)
            <button type="button" class="auth-toggle" @click="show = !show"
                    :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'" :aria-pressed="show.toString()">
                <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 3.5l17 17"/><path d="M10.6 5.7A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3.2 3.9M6.4 7.4A16 16 0 0 0 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 4-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
        @endif
    </div>

    @if ($toggle)
        <p x-show="caps" x-cloak x-transition.opacity class="mt-2 flex items-center gap-1.5 text-[13px] text-amber-800" role="status">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5l8.5 15h-17L12 3.5z"/><path d="M12 10v4M12 16.8v.1"/></svg>
            Caps Lock sedang aktif
        </p>
    @endif

    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-2 text-[13px] text-stone-600">{{ $hint }}</p>
    @endif

    {{ $slot }}

    @if (!empty($messages))
        <div id="{{ $id }}-error" role="alert">
            <x-input-error :messages="$messages" class="mt-2" />
        </div>
    @endif
</div>
