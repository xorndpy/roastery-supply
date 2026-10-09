@extends('layouts.admin')

@section('title', 'Pengaturan')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Pengaturan Situs</h1>
    <p class="text-sm text-stone-500 mt-1">Semua teks di website (nama, kontak, rekening, halaman Tentang) diatur dari sini.</p>
</div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6 max-w-3xl">
    @csrf @method('PUT')

    @foreach($groups as $group => $fields)
        <section class="bg-white p-6 rounded-lg border border-stone-200">
            <h2 class="font-bold mb-4">{{ $group }}</h2>
            <div class="space-y-4">
                @foreach($fields as $key => $def)
                    @php $val = old($key, $values[$key] ?? ($def['default'] ?? '')); @endphp
                    <div>
                        <label class="block text-sm font-medium mb-1.5" for="f-{{ $key }}">{{ $def['label'] }}</label>

                        @if($def['type'] === 'textarea')
                            <textarea id="f-{{ $key }}" name="{{ $key }}" rows="{{ $def['rows'] ?? 3 }}" maxlength="{{ $def['max'] ?? 500 }}"
                                      class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ $val }}</textarea>
                        @elseif($def['type'] === 'image')
                            @if(!empty($values[$key]))
                                <img src="{{ asset('storage/' . $values[$key]) }}" alt="" class="h-16 mb-2 rounded border border-stone-200">
                                <label class="flex items-center gap-2 text-xs text-stone-500 mb-2">
                                    <input type="checkbox" name="remove_{{ $key }}" value="1"> Hapus gambar ini
                                </label>
                            @endif
                            <input id="f-{{ $key }}" type="file" name="{{ $key }}" accept="image/jpeg,image/png,image/webp"
                                   class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
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

    @can('edit_settings')
        <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">Simpan Pengaturan</button>
    @endcan
</form>

@endsection
