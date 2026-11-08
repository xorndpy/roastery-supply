@extends('layouts.admin')

@section('title', 'Hero Beranda')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Hero Beranda</h1>
    <p class="text-sm text-stone-500 mt-1">
        Foto latar slideshow di bagian paling atas beranda. Judul dan teksnya diatur di
        <a href="{{ route('admin.settings.index', ['tab' => 'tampilan']) }}" class="text-orange-600 hover:underline">Pengaturan &rarr; Tampilan</a>.
    </p>
</div>

@if (session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@can('edit_settings')
<form method="POST" action="{{ route('admin.hero.store') }}" enctype="multipart/form-data"
      x-data="{ previews: [] }" class="bg-white p-5 rounded-lg border border-stone-200 mb-8">
    @csrf
    <h2 class="font-bold mb-1">Tambah foto</h2>
    <p class="text-xs text-stone-500 mb-3">
        Boleh pilih banyak foto sekaligus (maks. {{ $max }} foto di slideshow, tiap foto maks. 3 MB).
        Ideal 1920 x 1000 px, JPG/WEBP di bawah 400 KB. Bagian kiri foto tertutup teks, jadi taruh objek utama di sisi kanan.
    </p>

    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
           class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm"
           @change="previews = [...$event.target.files].map(f => URL.createObjectURL(f))">

    <div class="mt-3 flex flex-wrap gap-2" x-show="previews.length" x-cloak>
        <template x-for="src in previews" :key="src">
            <img :src="src" alt="" class="h-16 w-28 object-cover rounded border border-stone-200">
        </template>
    </div>

    <button class="mt-4 bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-lg font-medium disabled:opacity-50"
            :disabled="!previews.length" x-bind:disabled="!previews.length">Unggah</button>
</form>
@endcan

<div class="flex items-baseline justify-between mb-3">
    <h2 class="font-bold">Slideshow saat ini</h2>
    <span class="text-sm text-stone-500">{{ $slides->count() }} / {{ $max }} foto</span>
</div>

@if($slides->isEmpty())
    <div class="bg-white p-10 rounded-lg border border-stone-200 text-center text-stone-500">
        Belum ada foto. Beranda memakai latar gradasi coklat.
    </div>
@else
    <p class="text-xs text-stone-500 mb-3">Seret kartu (atau pakai tombol panah) untuk mengubah urutan. Foto pertama tampil lebih dulu dan juga dipakai di halaman login. Satu foto aktif = tanpa slideshow.</p>

    <ul id="hero-list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($slides as $s)
            <li draggable="true" data-id="{{ $s->id }}" class="bg-white rounded-lg border border-stone-200 overflow-hidden {{ $s->is_active ? '' : 'opacity-60' }}">
                <div class="relative">
                    <img src="{{ media_url($s->image) }}" alt="Foto hero {{ $loop->iteration }}" class="w-full aspect-[16/9] object-cover">
                    <span data-badge class="absolute left-2 top-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">#{{ $loop->iteration }}</span>
                    @unless($s->is_active)<span class="absolute right-2 top-2 rounded bg-stone-800 px-2 py-0.5 text-xs text-white">Nonaktif</span>@endunless
                </div>
                <div class="flex items-center justify-between gap-2 p-3 text-sm">
                    @can('edit_settings')
                    <div class="flex gap-1">
                        <button type="button" data-move="-1" class="px-2 py-1 rounded border border-stone-300 hover:bg-stone-50" aria-label="Geser ke depan">&larr;</button>
                        <button type="button" data-move="1" class="px-2 py-1 rounded border border-stone-300 hover:bg-stone-50" aria-label="Geser ke belakang">&rarr;</button>
                    </div>
                    <div class="flex items-center gap-3">
                        <form method="POST" action="{{ route('admin.hero.toggle', $s->id) }}">
                            @csrf @method('PATCH')
                            <button class="text-orange-600 hover:underline">{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.hero.destroy', $s->id) }}" onsubmit="return confirm('Hapus foto ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Hapus</button>
                        </form>
                    </div>
                    @endcan
                </div>
            </li>
        @endforeach
    </ul>
@endif

@can('edit_settings')
<script>
(() => {
    const list = document.getElementById('hero-list');
    if (!list) return;
    let dragging = null;

    const save = () => {
        const items = [...list.querySelectorAll('li[data-id]')];
        items.forEach((li, i) => { const b = li.querySelector('[data-badge]'); if (b) b.textContent = '#' + (i + 1); });
        fetch(@json(route('admin.hero.reorder')), {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
            body: JSON.stringify({ids: items.map(li => Number(li.dataset.id))})
        });
    };

    list.addEventListener('dragstart', e => { dragging = e.target.closest('li[data-id]'); });
    list.addEventListener('dragover', e => {
        e.preventDefault();
        const over = e.target.closest('li[data-id]');
        if (!dragging || !over || over === dragging) return;
        const r = over.getBoundingClientRect();
        const after = e.clientY > r.bottom || (e.clientY >= r.top && e.clientX > r.left + r.width / 2);
        list.insertBefore(dragging, after ? over.nextSibling : over);
    });
    list.addEventListener('dragend', () => { dragging = null; save(); });

    // Tombol panah (untuk layar sentuh)
    list.addEventListener('click', e => {
        const btn = e.target.closest('[data-move]');
        if (!btn) return;
        const li = btn.closest('li[data-id]');
        const dir = Number(btn.dataset.move);
        const sibling = dir < 0 ? li.previousElementSibling : li.nextElementSibling;
        if (!sibling) return;
        list.insertBefore(li, dir < 0 ? sibling : sibling.nextSibling);
        save();
    });
})();
</script>
@endcan

@endsection
