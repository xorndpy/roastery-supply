@extends('layouts.admin')

@php $isEdit = $faq->exists; @endphp
@section('title', $isEdit ? 'Edit FAQ' : 'Tambah FAQ')

@section('content')

<div class="mb-6"><a href="{{ route('admin.faqs.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke FAQ</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.faqs.update', $faq->id) : route('admin.faqs.store') }}" class="max-w-2xl bg-white p-6 rounded-lg border border-stone-200 space-y-4">
    @csrf @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1.5">Kategori *</label>
            <input type="text" name="category" list="faq-categories" value="{{ old('category', $faq->category) }}" required maxlength="100" class="w-full border border-stone-300 rounded-lg px-3 py-2">
            <datalist id="faq-categories">@foreach($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Urutan</label>
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $faq->sort_order ?? 0) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Pertanyaan *</label>
        <input type="text" name="question" value="{{ old('question', $faq->question) }}" required maxlength="255" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Jawaban *</label>
        <textarea name="answer" rows="6" required maxlength="5000" class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ old('answer', $faq->answer) }}</textarea>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $faq->is_active))> Tampilkan di website</label>
    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">{{ $isEdit ? 'Simpan' : 'Tambah' }}</button>
</form>
@endsection
