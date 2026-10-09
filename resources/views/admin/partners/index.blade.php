@extends('layouts.admin')

@section('title', 'Partner')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Partner & Merek Rekanan</h1>
        <p class="text-sm text-stone-500 mt-1">Logo tampil di beranda sesuai urutan.</p>
    </div>
    @can('create_partners')<a href="{{ route('admin.partners.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Tambah Partner</a>@endcan
</div>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Logo</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Link</th><th class="px-4 py-3">Urutan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($partners as $p)
            <tr>
                <td class="px-4 py-3"><img src="{{ asset('storage/' . $p->logo) }}" alt="{{ $p->name }}" class="h-10 max-w-[120px] object-contain"></td>
                <td class="px-4 py-3 font-medium">{{ $p->name }}</td>
                <td class="px-4 py-3 text-stone-500 truncate max-w-[220px]">{{ $p->link ?: '-' }}</td>
                <td class="px-4 py-3">{{ $p->sort_order }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $p->is_active ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-600' }}">{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @can('edit_partners')<a href="{{ route('admin.partners.edit', $p->id) }}" class="text-orange-600 hover:underline mr-3">Edit</a>@endcan
                    @can('delete_partners')
                    <form method="POST" action="{{ route('admin.partners.destroy', $p->id) }}" class="inline" onsubmit="return confirm('Hapus partner ini?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada partner.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
