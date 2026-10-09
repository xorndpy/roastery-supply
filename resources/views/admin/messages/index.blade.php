@extends('layouts.admin')

@section('title', 'Pesan Masuk')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Pesan Masuk</h1>
    <p class="text-sm text-stone-500 mt-1">Dari formulir Hubungi Kami. @if($unread)<strong class="text-amber-600">{{ $unread }} belum dibaca.</strong>@endif</p>
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / email / subjek" class="flex-1 min-w-[200px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="status" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua</option>
        <option value="unread" @selected(request('status') === 'unread')>Belum dibaca</option>
    </select>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

<div class="bg-white rounded-lg border border-stone-200 divide-y divide-stone-100">
@forelse($messages as $m)
    <a href="{{ route('admin.messages.show', $m->id) }}" class="block p-4 hover:bg-stone-50 {{ $m->is_read ? '' : 'bg-amber-50/40' }}">
        <div class="flex justify-between gap-4">
            <div class="min-w-0">
                <div class="{{ $m->is_read ? '' : 'font-bold' }} truncate">{{ $m->subject ?: '(tanpa subjek)' }}</div>
                <div class="text-sm text-stone-500 truncate">{{ $m->name }} · {{ $m->email }}</div>
                <div class="text-sm text-stone-600 mt-1 line-clamp-1">{{ $m->message }}</div>
            </div>
            <div class="text-right text-xs text-stone-400 whitespace-nowrap">
                {{ $m->created_at->format('d M Y H:i') }}
                @if($m->replied_at)<div class="text-green-700 mt-1">Dibalas</div>@endif
            </div>
        </div>
    </a>
@empty
    <div class="p-10 text-center text-stone-500">Belum ada pesan.</div>
@endforelse
</div>

<div class="mt-4">{{ $messages->links() }}</div>
@endsection
