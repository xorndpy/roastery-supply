@extends('layouts.admin')

@section('title', 'Hak Akses: ' . $role->name)

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.roles.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Roles</a>
    <h1 class="text-2xl font-bold text-stone-900 mt-2">Hak Akses: {{ $role->name }}</h1>
</div>

@if($locked)
    <div class="mb-6 p-4 bg-stone-100 border border-stone-200 text-stone-700 rounded-lg text-sm">
        Role ini dikunci dan tidak bisa diubah{{ $role->name === 'super_admin' ? ' (selalu punya semua izin)' : '' }}.
    </div>
@endif

<form method="POST" action="{{ route('admin.roles.update', $role->id) }}">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($groups as $group => $perms)
            <fieldset class="bg-white p-5 rounded-lg border border-stone-200" data-group>
                <div class="flex justify-between items-center mb-3">
                    <legend class="font-bold">{{ $group }}</legend>
                    @unless($locked)
                        <button type="button" class="text-xs text-orange-600 hover:underline" data-toggle-group>Pilih semua</button>
                    @endunless
                </div>
                <div class="space-y-2">
                    @foreach($perms as $perm)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $perm['name'] }}"
                                   @checked($role->name === 'super_admin' || in_array($perm['name'], $granted, true))
                                   @disabled($locked || $group === 'Administrasi')
                                   class="rounded border-stone-300">
                            <span class="capitalize">{{ $perm['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                @if($group === 'Administrasi' && !$locked)
                    <p class="text-xs text-stone-500 mt-3">Khusus Super Admin, tidak bisa diberikan ke role lain.</p>
                @endif
            </fieldset>
        @endforeach
    </div>

    @unless($locked)
        <div class="mt-6">
            <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">Simpan Hak Akses</button>
        </div>
    @endunless
</form>

<script>
    document.querySelectorAll('[data-toggle-group]').forEach(btn => {
        btn.addEventListener('click', () => {
            const boxes = [...btn.closest('[data-group]').querySelectorAll('input[type=checkbox]:not(:disabled)')];
            const all = boxes.every(b => b.checked);
            boxes.forEach(b => b.checked = !all);
        });
    });
</script>

@endsection
