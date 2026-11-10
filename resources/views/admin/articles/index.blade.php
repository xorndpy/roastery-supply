@extends('layouts.admin')

@section('title', 'Artikel')

@section('content')
@php
    $tabs = ['' => ['Semua', $counts['semua']], 'terbit' => ['Terbit', $counts['terbit']], 'draft' => ['Draft', $counts['draft']], 'terjadwal' => ['Terjadwal', null]];
    $cur = request('status', '');
@endphp
<div class="dash">
    <header class="dash-head">
        <div>
            <p class="dash-eyebrow">Konten</p>
            <h1 class="dash-h1">Artikel</h1>
            <p class="dash-lede">Tulisan untuk halaman Insight dan beranda: panduan, tips perawatan, dan berita seputar kopi.</p>
        </div>
        @can('create_articles')<a href="{{ route('admin.articles.create') }}" class="pay-btn is-primary !px-5 !py-2.5">+ Tulis artikel</a>@endcan
    </header>

    <nav class="pay-tabs" aria-label="Status artikel">
        @foreach($tabs as $key => [$label, $n])
            <a href="{{ route('admin.articles.index', array_filter(['status' => $key, 'search' => request('search')])) }}" class="pay-tab" @if($cur === $key) aria-current="true" @endif>{{ $label }} @if($n !== null)<b>{{ $n }}</b>@endif</a>
        @endforeach
    </nav>

    <form method="GET" class="mb-4 flex gap-3">
        <input type="hidden" name="status" value="{{ $cur }}">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari judul…" class="w-72 rounded-lg border border-stone-300 px-3 py-2 text-sm" aria-label="Cari judul">
        <button class="pay-btn is-primary">Cari</button>
    </form>

    <section class="dash-card">
        <div class="dash-tablewrap overflow-x-auto" style="margin-top:-.25rem;border-top:0">
            <table class="dash-table">
                <thead><tr><th>Artikel</th><th>Status</th><th>Tayang</th><th class="text-right">Dibaca</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse($articles as $a)
                        @php $scheduled = $a->is_published && $a->published_at && $a->published_at->isFuture(); @endphp
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if($a->cover_url)<img src="{{ $a->cover_url }}" alt="" class="h-12 w-16 rounded-md border border-stone-200 object-cover">@else<span class="flex h-12 w-16 items-center justify-center rounded-md bg-stone-100 text-[10px] text-stone-400">Tanpa foto</span>@endif
                                    <div class="min-w-0">
                                        <p class="max-w-md truncate font-medium text-stone-900">{{ $a->title }}</p>
                                        <p class="text-xs text-stone-500">{{ $a->category }}@if($a->is_featured) · <span class="text-orange-700">Slider</span>@endif @if($a->video_url) · Video @endif</p>
                                    </div>
                                </div>
                            </td>
                            <td><span class="dash-badge" style="--c: {{ $scheduled ? '#c9902e' : ($a->is_published ? '#5b7f4b' : '#a8998b') }}"><i></i>{{ $scheduled ? 'Terjadwal' : ($a->is_published ? 'Terbit' : 'Draft') }}</span></td>
                            <td class="text-xs text-stone-500">{{ $a->published_at?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H:i') ?? '-' }}</td>
                            <td class="text-right tabular-nums text-stone-600">{{ number_format($a->views) }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($a->is_published && !$scheduled)<a href="{{ route('articles.show', $a->slug) }}" target="_blank" rel="noopener" class="pay-btn is-quiet !py-1.5">Lihat</a>@endif
                                    @can('edit_articles')
                                        <form method="POST" action="{{ route('admin.articles.toggle-publish', $a->id) }}">@csrf @method('PATCH')<button class="pay-btn is-quiet !py-1.5">{{ $a->is_published ? 'Jadikan draft' : 'Terbitkan' }}</button></form>
                                        <a href="{{ route('admin.articles.edit', $a->id) }}" class="pay-btn is-primary !py-1.5">Edit</a>
                                    @endcan
                                    @can('delete_articles')
                                        <form method="POST" action="{{ route('admin.articles.destroy', $a->id) }}" data-confirm-title="Hapus artikel ini?" data-confirm-text="Artikel tidak akan tampil lagi di website." data-confirm-ok="Ya, hapus artikel" data-confirm-tone="danger">@csrf @method('DELETE')<button class="pay-btn is-quiet !py-1.5 !text-red-700">Hapus</button></form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-14 text-center text-stone-500">Belum ada artikel. Mulai dengan menulis artikel pertama.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-5">{{ $articles->links() }}</div>
</div>
@endsection
