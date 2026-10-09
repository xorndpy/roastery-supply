@extends('layouts.admin')

@php $isEdit = $testimonial->exists; @endphp
@section('title', $isEdit ? 'Edit Testimoni' : 'Tambah Testimoni')

@section('content')

<div class="mb-6"><a href="{{ route('admin.testimonials.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Testimoni</a></div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" enctype="multipart/form-data"
      action="{{ $isEdit ? route('admin.testimonials.update', $testimonial->id) : route('admin.testimonials.store') }}"
      class="max-w-2xl bg-white p-6 rounded-lg border border-stone-200 space-y-4">
    @csrf @if($isEdit) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1.5">Nama pelanggan / usaha *</label>
        <input type="text" name="customer_name" value="{{ old('customer_name', $testimonial->customer_name) }}" required maxlength="100" class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Rating *</label>
        <select name="rating" class="border border-stone-300 rounded-lg px-3 py-2">
            @for($i = 5; $i >= 1; $i--)<option value="{{ $i }}" @selected((int) old('rating', $testimonial->rating) === $i)>{{ $i }} bintang</option>@endfor
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Isi testimoni *</label>
        <textarea name="content" rows="5" required minlength="10" maxlength="1000" class="w-full border border-stone-300 rounded-lg px-3 py-2">{{ old('content', $testimonial->content) }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1.5">Foto (opsional)</label>
        @if($testimonial->customer_photo)<img src="{{ asset('storage/' . $testimonial->customer_photo) }}" alt="" class="h-16 w-16 object-cover rounded-full mb-2">@endif
        <input type="file" name="customer_photo" accept="image/jpeg,image/png,image/webp" class="w-full border border-stone-300 rounded-lg px-3 py-2 text-sm">
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_approved" value="1" @checked(old('is_approved', $testimonial->is_approved))> Tampilkan di website</label>

    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">{{ $isEdit ? 'Simpan' : 'Tambah' }}</button>
</form>
@endsection
