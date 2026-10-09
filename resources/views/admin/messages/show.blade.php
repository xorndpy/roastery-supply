@extends('layouts.admin')

@section('title', 'Pesan dari ' . $message->name)

@section('content')

<div class="mb-6"><a href="{{ route('admin.messages.index') }}" class="text-sm text-stone-500 hover:text-stone-700">← Kembali ke Pesan Masuk</a></div>

<div class="max-w-3xl bg-white p-6 rounded-lg border border-stone-200">
    <h1 class="text-xl font-bold">{{ $message->subject ?: '(tanpa subjek)' }}</h1>
    <div class="text-sm text-stone-500 mt-2">
        {{ $message->name }} ·
        <a href="mailto:{{ $message->email }}" class="text-orange-600 hover:underline">{{ $message->email }}</a>
        @if($message->phone) · {{ $message->phone }}@endif
        · {{ $message->created_at->format('d M Y H:i') }}
    </div>

    <div class="mt-6 whitespace-pre-line text-stone-800">{{ $message->message }}</div>

    <div class="mt-8 pt-4 border-t border-stone-100 flex flex-wrap gap-3 items-center">
        <a href="mailto:{{ $message->email }}?subject={{ rawurlencode('Re: ' . ($message->subject ?: 'Pesan Anda')) }}"
           class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-lg text-sm font-medium">Balas via Email</a>

        @can('reply_contact_messages')
        <form method="POST" action="{{ route('admin.messages.mark-replied', $message->id) }}">
            @csrf @method('PATCH')
            <button class="border border-stone-300 hover:bg-stone-50 px-4 py-2 rounded-lg text-sm">{{ $message->replied_at ? 'Batalkan tanda dibalas' : 'Tandai sudah dibalas' }}</button>
        </form>
        <form method="POST" action="{{ route('admin.messages.mark-as-read', $message->id) }}">
            @csrf @method('PATCH')
            <button class="border border-stone-300 hover:bg-stone-50 px-4 py-2 rounded-lg text-sm">Tandai belum dibaca</button>
        </form>
        @endcan

        @can('delete_contact_messages')
        <form method="POST" action="{{ route('admin.messages.destroy', $message->id) }}" onsubmit="return confirm('Hapus pesan ini?')" class="ml-auto">
            @csrf @method('DELETE')
            <button class="text-red-600 hover:underline text-sm">Hapus</button>
        </form>
        @endcan
    </div>
    @if($message->replied_at)<p class="text-xs text-green-700 mt-3">Dibalas pada {{ $message->replied_at->format('d M Y H:i') }}</p>@endif
</div>
@endsection
