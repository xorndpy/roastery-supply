@extends('layouts.admin')

@section('title', 'Testimoni')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Testimoni</h1>
        <p class="text-sm text-stone-500 mt-1">Hanya testimoni yang disetujui yang tampil di website. @if($pending)<strong class="text-amber-600">{{ $pending }} menunggu tinjauan.</strong>@endif</p>
    </div>
    @can('approve_testimonials')
        <a href="{{ route('admin.testimonials.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Tambah Manual</a>
    @endcan
</div>

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-6 flex flex-wrap gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama / isi" class="flex-1 min-w-[200px] border border-stone-300 rounded-lg px-3 py-2">
    <select name="status" class="border border-stone-300 rounded-lg px-3 py-2">
        <option value="">Semua</option>
        <option value="pending" @selected(request('status') === 'pending')>Menunggu</option>
        <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
    </select>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Filter</button>
</form>

<div class="space-y-3">
@forelse($testimonials as $t)
    <div class="bg-white p-5 rounded-lg border border-stone-200">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <div class="font-medium">{{ $t->customer_name }}
                    <span class="text-amber-500 ml-2" aria-label="{{ $t->rating }} dari 5">{{ str_repeat('★', $t->rating) }}<span class="text-stone-300">{{ str_repeat('★', 5 - $t->rating) }}</span></span>
                </div>
                <div class="text-xs text-stone-400">{{ $t->created_at->format('d M Y') }}@if($t->order_id) · dari pesanan #{{ $t->order_id }}@else · input manual @endif</div>
            </div>
            <span class="self-start px-2 py-0.5 rounded text-xs {{ $t->is_approved ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                {{ $t->is_approved ? 'Tampil' : 'Menunggu' }}
            </span>
        </div>
        <p class="text-stone-700 mt-3 whitespace-pre-line">{{ $t->content }}</p>
        <div class="flex gap-4 mt-4 text-sm">
            @can('approve_testimonials')
            <form method="POST" action="{{ route('admin.testimonials.toggle-approval', $t->id) }}">
                @csrf @method('PATCH')
                <button class="{{ $t->is_approved ? 'text-stone-600' : 'text-green-700 font-medium' }} hover:underline">{{ $t->is_approved ? 'Sembunyikan' : 'Setujui & tampilkan' }}</button>
            </form>
            <a href="{{ route('admin.testimonials.edit', $t->id) }}" class="text-orange-600 hover:underline">Edit</a>
            @endcan
            @can('delete_testimonials')
            <form method="POST" action="{{ route('admin.testimonials.destroy', $t->id) }}" onsubmit="return confirm('Hapus testimoni ini?')">
                @csrf @method('DELETE')
                <button class="text-red-600 hover:underline">Hapus</button>
            </form>
            @endcan
        </div>
    </div>
@empty
    <div class="bg-white p-10 rounded-lg border border-stone-200 text-center text-stone-500">Belum ada testimoni.</div>
@endforelse
</div>

<div class="mt-4">{{ $testimonials->links() }}</div>
@endsection
