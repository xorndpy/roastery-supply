@extends('layouts.admin')

@section('title', 'FAQ')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">FAQ</h1>
        <p class="text-sm text-stone-500 mt-1">Dikelompokkan per kategori. Urutan mengikuti angka urut.</p>
    </div>
    @can('create_faqs')<a href="{{ route('admin.faqs.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Tambah FAQ</a>@endcan
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari pertanyaan / jawaban" class="flex-1 min-w-[200px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="category" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua Kategori</option>
        @foreach($categories as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>@endforeach
    </select>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

@forelse($faqs as $category => $items)
    <section class="mb-6">
        <h2 class="font-bold text-stone-700 mb-2">{{ $category }}</h2>
        <div class="bg-white rounded-lg border border-stone-200 divide-y divide-stone-100">
            @foreach($items as $f)
                <div class="p-4 flex items-start gap-4">
                    <span class="text-xs text-stone-400 w-8 pt-1">#{{ $f->sort_order }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium">{{ $f->question }} @unless($f->is_active)<span class="ml-2 px-2 py-0.5 rounded bg-stone-100 text-stone-500 text-xs">Nonaktif</span>@endunless</div>
                        <div class="text-sm text-stone-600 mt-1 line-clamp-2">{{ $f->answer }}</div>
                    </div>
                    <div class="whitespace-nowrap text-sm">
                        @can('edit_faqs')<a href="{{ route('admin.faqs.edit', $f->id) }}" class="text-orange-600 hover:underline mr-3">Edit</a>@endcan
                        @can('delete_faqs')
                        <form method="POST" action="{{ route('admin.faqs.destroy', $f->id) }}" class="inline" onsubmit="return confirm('Hapus FAQ ini?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button></form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@empty
    <div class="bg-white p-10 rounded-lg border border-stone-200 text-center text-stone-500">Belum ada FAQ.</div>
@endforelse
@endsection
