@php
    $type = $def['type'];
    $isImage = $type === 'image';
    $wide = $type === 'textarea' || ($isImage && $group !== 'Header Halaman') || ($type === 'text' && ($def['max'] ?? 255) > 120);
    $ratio = str_replace('/', ' / ', $def['ratio'] ?? '16/9');
    $inputCls = 'w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm';
@endphp
<div class="{{ $wide ? 'md:col-span-2' : '' }}">
    <label class="mb-1.5 block text-sm font-medium text-stone-800" for="f-{{ $key }}">{{ $def['label'] }}</label>

    @if($type === 'textarea')
        <textarea id="f-{{ $key }}" name="{{ $key }}" rows="{{ $def['rows'] ?? 3 }}" maxlength="{{ $def['max'] ?? 500 }}" class="{{ $inputCls }}">{{ $val }}</textarea>

    @elseif($type === 'select')
        <select id="f-{{ $key }}" name="{{ $key }}" class="{{ $inputCls }}">
            @foreach($def['options'] as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $val === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>

    @elseif($isImage)
        <div x-data="{ src: null, removed: false, existing: @js(!empty($values[$key]) ? media_url($values[$key]) : null) }"
             class="{{ ($def['ratio'] ?? '') === '1/1' ? 'max-w-[11rem]' : '' }}">
            <div class="relative overflow-hidden rounded-xl border bg-stone-50"
                 :class="(src || (existing && !removed)) ? 'border-stone-200' : 'border-dashed border-stone-300'"
                 style="aspect-ratio: {{ $ratio }}">
                <img x-show="src || (existing && !removed)" x-cloak :src="src || existing" alt="Pratinjau {{ $def['label'] }}" class="absolute inset-0 h-full w-full object-cover">
                <div x-show="!(src || (existing && !removed))" class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-stone-500">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 17l5-4.5 4 3.5 3-2.5 4 3.5"/></svg>
                    <span class="text-xs">Belum ada foto</span>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <label class="inline-flex cursor-pointer items-center rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700 hover:bg-stone-50 focus-within:ring-2 focus-within:ring-orange-500">
                    <input x-ref="file" id="f-{{ $key }}" type="file" name="{{ $key }}" accept="image/jpeg,image/png,image/webp" class="sr-only"
                           @change="const f = $event.target.files[0]; src = f ? URL.createObjectURL(f) : null; if (f) removed = false; dirty = true">
                    <span x-text="(src || (existing && !removed)) ? 'Ganti foto' : 'Pilih foto'"></span>
                </label>
                <button type="button" x-show="src" x-cloak @click="src = null; $refs.file.value = ''" class="text-xs font-medium text-stone-600 hover:underline">Batalkan pilihan</button>
                <button type="button" x-show="existing && !src && !removed" x-cloak @click="removed = true; dirty = true" class="text-xs font-medium text-red-700 hover:underline">Hapus foto</button>
                <button type="button" x-show="removed" x-cloak @click="removed = false" class="text-xs font-medium text-stone-600 hover:underline">Batalkan hapus</button>
                <template x-if="removed"><input type="hidden" name="remove_{{ $key }}" value="1"></template>
            </div>
        </div>

    @else
        <input id="f-{{ $key }}" type="{{ in_array($type, ['url', 'email', 'number']) ? $type : 'text' }}" @if($type === 'number') min="0" step="1000" inputmode="numeric" @endif
               name="{{ $key }}" value="{{ $val }}" maxlength="{{ $def['max'] ?? 255 }}" class="{{ $inputCls }}">
    @endif

    @if(!empty($def['help']))<p class="mt-1.5 text-xs leading-relaxed text-stone-500">{{ $def['help'] }}</p>@endif
</div>
