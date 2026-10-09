@extends('layouts.admin')

@php $isEdit = $user->exists; @endphp

@section('title', $isEdit ? 'Edit User: ' . $user->name : 'Tambah User')

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.users.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Daftar User</a>
</div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
        <div class="font-medium mb-2">Ada kesalahan:</div>
        <ul class="list-disc list-inside text-sm">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.users.update', $user->id) : route('admin.users.store') }}"
      class="max-w-2xl bg-white p-6 rounded-lg border border-stone-200 space-y-4">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div>
        <label class="block text-sm font-medium mb-1.5">Nama *</label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
               class="w-full border border-stone-300 rounded-lg px-3 py-2">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Email *</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Telepon</label>
            <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                   class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1.5">Role *</label>
        <select name="role" required class="w-full border border-stone-300 rounded-lg px-3 py-2"
                @disabled($isEdit && $user->id === auth()->id())>
            @foreach($roles as $r)
                <option value="{{ $r->name }}" @selected(old('role', $currentRole) === $r->name)>{{ $r->name }}</option>
            @endforeach
        </select>
        @if($isEdit && $user->id === auth()->id())
            <input type="hidden" name="role" value="{{ $currentRole }}">
            <p class="text-xs text-stone-500 mt-1">Role akunmu sendiri tidak bisa diubah.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1.5">Password {{ $isEdit ? '(kosongkan jika tidak diganti)' : '*' }}</label>
            <input type="password" name="password" {{ $isEdit ? '' : 'required' }} autocomplete="new-password"
                   class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1.5">Ulangi Password</label>
            <input type="password" name="password_confirmation" autocomplete="new-password"
                   class="w-full border border-stone-300 rounded-lg px-3 py-2">
        </div>
    </div>

    <button class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2.5 rounded-lg font-medium">
        {{ $isEdit ? 'Simpan Perubahan' : 'Buat User' }}
    </button>
</form>

@endsection
