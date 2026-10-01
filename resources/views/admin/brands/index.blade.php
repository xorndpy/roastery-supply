@extends('layouts.admin')

@section('title', 'Merek')

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Merek Produk</h1>
        <p class="text-sm text-stone-500 mt-1">Kelola merek mesin kopi.</p>
    </div>
    <a href="{{ route('admin.brands.create') }}"
       class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium transition">
        + Tambah Merek
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-3 gap-3 mb-6">
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Total Merek</div>
        <div class="text-2xl font-bold mt-1">{{ $stats['total'] }}</div>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Aktif</div>
        <div class="text-2xl font-bold text-green-600 mt-1">{{ $stats['active'] }}</div>
    </div>
    <div class="bg-white p-4 rounded-lg border border-stone-200">
        <div class="text-xs text-stone-500">Nonaktif</div>
        <div class="text-2xl font-bold text-stone-400 mt-1">{{ $stats['inactive'] }}</div>
    </div>
</div>

{{-- Filter --}}
<div class="bg-white p-4 rounded-lg border border-stone-200 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Cari nama merek..."
               class="md:col-span-2 border border-stone-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500">

        <select name="status" class="border border-stone-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Semua Status</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Nonaktif</option>
        </select>

        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-stone-800 hover:bg-stone-900 text-white px-4 py-2 rounded-lg text-sm font-medium">
                Filter
            </button>
            <a href="{{ route('admin.brands.index') }}"
               class="px-4 py-2 border border-stone-300 rounded-lg text-sm hover:bg-stone-50">
                Reset
            </a>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="bg-white rounded-lg border border-stone-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-stone-600 w-20">Logo</th>
                    <th class="px-4 py-3 text-left font-medium text-stone-600">Nama</th>
                    <th class="px-4 py-3 text-left font-medium text-stone-600">Deskripsi</th>
                    <th class="px-4 py-3 text-center font-medium text-stone-600">Produk</th>
                    <th class="px-4 py-3 text-center font-medium text-stone-600">Status</th>
                    <th class="px-4 py-3 text-right font-medium text-stone-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-200">
                @forelse($brands as $brand)
                    <tr class="hover:bg-stone-50">
                        <td class="px-4 py-3">
                            <div class="w-12 h-12 rounded bg-stone-100 overflow-hidden">
                                @if($brand->logo)
                                    <img src="{{ asset('storage/' . $brand->logo) }}"
                                         alt="{{ $brand->name }}"
                                         class="w-full h-full object-contain">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-stone-400 text-xs">
                                        N/A
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-stone-900">{{ $brand->name }}</div>
                            <div class="text-xs text-stone-400">{{ $brand->slug }}</div>
                        </td>
                        <td class="px-4 py-3 text-stone-600 text-sm">
                            {{ Str::limit($brand->description, 60) ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs px-2 py-1 rounded-full bg-stone-100 text-stone-700">
                                {{ $brand->products_count }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <form method="POST" action="{{ route('admin.brands.toggle-active', $brand->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="text-xs px-2 py-1 rounded-full font-medium transition
                                               {{ $brand->is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}">
                                    {{ $brand->is_active ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.brands.edit', $brand->id) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                                <form method="POST" action="{{ route('admin.brands.destroy', $brand->id) }}"
                                      onsubmit="return confirm('Yakin hapus merek {{ $brand->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center text-stone-500">
                            <div class="text-4xl mb-3">🔖</div>
                            <p class="mb-3">Belum ada merek.</p>
                            <a href="{{ route('admin.brands.create') }}"
                               class="inline-block bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-lg text-sm font-medium">
                                + Tambah Merek
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">
    {{ $brands->links() }}
</div>

@endsection