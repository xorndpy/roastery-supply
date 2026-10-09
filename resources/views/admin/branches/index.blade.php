@extends('layouts.admin')

@section('title', 'Cabang')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Cabang & Lokasi</h1>
        <p class="text-sm text-stone-500 mt-1">Cabang dengan koordinat akan tampil beserta peta di halaman Cabang.</p>
    </div>
    @can('create_branches')<a href="{{ route('admin.branches.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Tambah Cabang</a>@endcan
</div>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Cabang</th><th class="px-4 py-3">Alamat</th><th class="px-4 py-3">Telepon</th><th class="px-4 py-3">Peta</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($branches as $b)
            <tr>
                <td class="px-4 py-3 font-medium">{{ $b->name }}</td>
                <td class="px-4 py-3 text-stone-600 max-w-xs">{{ $b->address }}</td>
                <td class="px-4 py-3">{{ $b->phone ?: '-' }}</td>
                <td class="px-4 py-3">{!! $b->latitude !== null ? '<span class="text-green-700">Ada</span>' : '<span class="text-stone-400">Tanpa koordinat</span>' !!}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs {{ $b->is_active ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-600' }}">{{ $b->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @can('edit_branches')<a href="{{ route('admin.branches.edit', $b->id) }}" class="text-orange-600 hover:underline mr-3">Edit</a>@endcan
                    @can('delete_branches')
                    <form method="POST" action="{{ route('admin.branches.destroy', $b->id) }}" class="inline" onsubmit="return confirm('Hapus cabang ini?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada cabang.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
