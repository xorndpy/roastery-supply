@extends('layouts.shop')

@section('title', 'Buku Alamat')

@section('content')
<x-page-header plain title="Buku alamat" :crumbs="[['Beranda', route('home')], ['Profil', route('customer.profile.edit')], ['Buku alamat']]" />

<div class="container-x py-6 sm:py-10 pb-28">
    @if(session('success'))<p class="mb-4 rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm text-stone-800" role="status">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('error') }}</p>@endif

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-600">Simpan beberapa alamat kirim supaya checkout lebih cepat. Maksimal {{ $limit }} alamat.</p>
        @if($addresses->count() < $limit)
            <a href="{{ route('customer.addresses.create') }}" class="btn btn-primary">+ Tambah alamat</a>
        @endif
    </div>

    @if($addresses->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">
            Belum ada alamat tersimpan. Alamat dari checkout pertama Anda akan otomatis tersimpan di sini.
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($addresses as $a)
                <div class="flex flex-col rounded-xl border border-stone-200 bg-white p-4 sm:p-5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-stone-900">{{ $a->label }}</h3>
                        @if($a->is_default)<span class="rounded-md border border-stone-200 bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-700">Utama</span>@endif
                    </div>
                    <p class="font-medium">{{ $a->recipient_name }}</p>
                    <p class="text-sm text-stone-500">{{ $a->phone }}</p>
                    <p class="mt-2 break-words text-sm leading-relaxed text-stone-700">{{ $a->address }}<br>{{ $a->regionLine() }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ route('customer.addresses.edit', $a->id) }}" class="btn btn-outline btn-sm">Ubah</a>
                        @unless($a->is_default)
                            <form method="POST" action="{{ route('customer.addresses.default', $a->id) }}">@csrf<button type="submit" class="btn btn-outline btn-sm">Jadikan utama</button></form>
                        @endunless
                        <form method="POST" action="{{ route('customer.addresses.destroy', $a->id) }}" class="ml-auto">
                            @csrf @method('DELETE')
                            <button type="submit" data-confirm-title="Hapus alamat &quot;{{ $a->label }}&quot;?" data-confirm-text="Pesanan lama tidak terpengaruh karena alamat pesanan disimpan terpisah." data-confirm-ok="Ya, hapus" data-confirm-tone="danger" class="btn btn-outline btn-sm text-red-700">Hapus</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
