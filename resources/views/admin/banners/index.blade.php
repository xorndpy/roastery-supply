@extends('layouts.admin')

@section('title', 'Banner')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Banner Beranda</h1>
        <p class="text-sm text-stone-500 mt-1">Seret baris untuk mengatur urutan. Banner tayang sesuai jadwal.</p>
    </div>
    @can('create_banners')
        <a href="{{ route('admin.banners.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Tambah Banner</a>
    @endcan
</div>

<ul id="banner-list" class="space-y-3">
@forelse($banners as $b)
    @php
        if (!$b->is_active) { $state = ['Nonaktif', 'bg-stone-100 text-stone-600']; }
        elseif ($b->start_at && $b->start_at->isFuture()) { $state = ['Terjadwal', 'bg-blue-100 text-blue-700']; }
        elseif ($b->end_at && $b->end_at->isPast()) { $state = ['Berakhir', 'bg-red-100 text-red-700']; }
        else { $state = ['Tayang', 'bg-green-100 text-green-700']; }
    @endphp
    <li draggable="true" data-id="{{ $b->id }}" class="bg-white p-4 rounded-lg border border-stone-200 flex items-center gap-4 cursor-move">
        <span class="text-stone-400 select-none" aria-hidden="true">⋮⋮</span>
        <img src="{{ asset('storage/' . $b->image) }}" alt="" class="w-40 h-20 object-cover rounded border border-stone-200">
        <div class="flex-1 min-w-0">
            <div class="font-medium truncate">{{ $b->title }}</div>
            <div class="text-xs text-stone-500 mt-1">
                @if($b->start_at || $b->end_at)
                    {{ $b->start_at?->format('d M Y H:i') ?? 'sekarang' }} → {{ $b->end_at?->format('d M Y H:i') ?? 'tanpa batas' }}
                @else
                    Selalu tayang
                @endif
            </div>
        </div>
        <span class="px-2 py-0.5 rounded text-xs {{ $state[1] }}">{{ $state[0] }}</span>
        <div class="whitespace-nowrap text-sm">
            @can('edit_banners')<a href="{{ route('admin.banners.edit', $b->id) }}" class="text-orange-600 hover:underline mr-3">Edit</a>@endcan
            @can('delete_banners')
            <form method="POST" action="{{ route('admin.banners.destroy', $b->id) }}" class="inline" onsubmit="return confirm('Hapus banner ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 hover:underline">Hapus</button>
            </form>
            @endcan
        </div>
    </li>
@empty
    <li class="bg-white p-10 rounded-lg border border-stone-200 text-center text-stone-500">Belum ada banner. Beranda akan menampilkan hero teks dari Pengaturan.</li>
@endforelse
</ul>

@can('edit_banners')
<script>
(() => {
    const list = document.getElementById('banner-list');
    let dragging = null;

    list.addEventListener('dragstart', e => { dragging = e.target.closest('li[data-id]'); });
    list.addEventListener('dragover', e => {
        e.preventDefault();
        const over = e.target.closest('li[data-id]');
        if (!dragging || !over || over === dragging) return;
        const after = e.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
        list.insertBefore(dragging, after ? over.nextSibling : over);
    });
    list.addEventListener('dragend', () => {
        dragging = null;
        const ids = [...list.querySelectorAll('li[data-id]')].map(li => Number(li.dataset.id));
        fetch(@json(route('admin.banners.reorder')), {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
            body: JSON.stringify({ids})
        });
    });
})();
</script>
@endcan

@endsection
