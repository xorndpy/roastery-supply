@extends('layouts.admin')

@php $isEdit = $expense !== null; @endphp
@section('title', $isEdit ? 'Ubah Biaya' : 'Catat Biaya')

@section('content')

<div class="mb-6"><a href="{{ route('admin.expenses.index') }}" class="text-sm text-stone-500 hover:text-stone-700">Kembali ke Biaya Operasional</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.expenses.update', $expense->id) : route('admin.expenses.store') }}" class="max-w-2xl bg-white p-6 rounded-lg border border-stone-200 space-y-5">
    @csrf @if($isEdit) @method('PUT') @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Tanggal *</label>
            <input type="date" name="expense_date" value="{{ old('expense_date', $isEdit ? $expense->expense_date->format('Y-m-d') : now('Asia/Jakarta')->toDateString()) }}" required class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Kategori *</label>
            <select name="category" required class="w-full border border-stone-300 rounded-lg px-3 py-2">
                @foreach(\App\Models\Expense::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected(old('category', $isEdit ? $expense->category : 'operasional') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1.5">Keterangan *</label>
        <input type="text" name="description" value="{{ old('description', $isEdit ? $expense->description : '') }}" required maxlength="255" placeholder="Mis. Sewa ruko Desember" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1.5">Jumlah (Rp) *</label>
        <input type="number" name="amount" value="{{ old('amount', $isEdit ? (int) $expense->amount : '') }}" required min="1" step="1" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1.5">Catatan</label>
        <textarea name="note" rows="3" maxlength="1000" class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ old('note', $isEdit ? $expense->note : '') }}</textarea>
    </div>

    <div class="flex gap-3">
        <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">{{ $isEdit ? 'Simpan perubahan' : 'Simpan biaya' }}</button>
        <a href="{{ route('admin.expenses.index') }}" class="px-6 py-2.5 rounded-lg border border-stone-300 text-stone-700">Batal</a>
    </div>
</form>
@endsection
