@extends('layouts.admin')

@section('title', $milestone->exists ? 'Edit Milestone' : 'Tambah Milestone')

@section('content')
<div class="dash max-w-2xl">
    <a href="{{ route('admin.milestones.index') }}" class="text-sm text-stone-500 hover:text-stone-800">← Kembali ke Milestone</a>
    <header class="dash-head" style="margin-top:.75rem"><div><h1 class="dash-h1" style="margin-top:0">{{ $milestone->exists ? 'Edit milestone' : 'Tambah milestone' }}</h1></div></header>

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $milestone->exists ? route('admin.milestones.update', $milestone->id) : route('admin.milestones.store') }}" class="dash-card space-y-4">
        @csrf
        @if($milestone->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
            <label class="block text-sm font-medium text-stone-700">Tahun / periode
                <input type="text" name="year" value="{{ old('year', $milestone->year) }}" required maxlength="30" placeholder="2026, Q3 2026, atau Berikutnya" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm">
            </label>
            <label class="block text-sm font-medium text-stone-700">Urutan
                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $milestone->sort_order) }}" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm">
            </label>
        </div>
        <label class="block text-sm font-medium text-stone-700">Judul
            <input type="text" name="title" value="{{ old('title', $milestone->title) }}" required maxlength="120" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2.5 text-sm">
        </label>
        <label class="block text-sm font-medium text-stone-700">Keterangan <span class="font-normal text-stone-500">(maks. 400 karakter)</span>
            <textarea name="description" rows="3" maxlength="400" class="mt-1 block w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $milestone->description) }}</textarea>
        </label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $milestone->is_active)) class="rounded border-stone-400"> Tampilkan di situs</label>
        <div class="flex gap-3"><button class="pay-btn is-primary !px-6 !py-2.5">Simpan</button><a href="{{ route('admin.milestones.index') }}" class="pay-btn is-quiet !py-2.5">Batal</a></div>
    </form>
</div>
@endsection
