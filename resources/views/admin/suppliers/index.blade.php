@extends('layouts.admin')

@section('title', 'Supplier')

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Supplier</h1>
        <p class="mt-1 text-sm text-stone-500">Pemasok barang untuk pembelian.</p>
    </div>
    @can('manage_stock')<a href="{{ route('admin.suppliers.create') }}" class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-800">+ Tambah supplier</a>@endcan
</div>

<form method="GET" class="mb-4 flex gap-2">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, kontak, atau telepon" class="w-full max-w-sm rounded-lg border border-stone-300 px-3 py-2 text-sm">
    <button class="rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white">Cari</button>
</form>

<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-xs text-stone-500">
            <tr><th class="px-4 py-3 font-medium">Supplier</th><th class="px-4 py-3 font-medium">Kontak</th><th class="px-4 py-3 font-medium">Telepon / email</th><th class="px-4 py-3 text-right font-medium">Pembelian</th><th class="px-4 py-3 font-medium">Status</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($suppliers as $s)
            <tr>
                <td class="px-4 py-3 font-medium text-stone-900">{{ $s->name }}</td>
                <td class="px-4 py-3 text-stone-600">{{ $s->contact_name ?: '-' }}</td>
                <td class="px-4 py-3 text-stone-600">{{ $s->phone ?: '-' }}@if($s->email)<div class="text-xs text-stone-500">{{ $s->email }}</div>@endif</td>
                <td class="px-4 py-3 text-right">{{ $s->purchase_orders_count }}</td>
                <td class="px-4 py-3"><span class="rounded-md border px-2 py-0.5 text-xs {{ $s->is_active ? 'border-stone-200 bg-stone-100 text-stone-700' : 'border-stone-200 bg-white text-stone-400' }}">{{ $s->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @can('manage_stock')
                        <a href="{{ route('admin.suppliers.edit', $s->id) }}" class="row-btn">Edit</a>
                        <form method="POST" action="{{ route('admin.suppliers.destroy', $s->id) }}" class="inline">@csrf @method('DELETE')
                            <button type="submit" data-confirm-title="Hapus supplier {{ $s->name }}?" data-confirm-text="Supplier yang sudah punya riwayat pembelian tidak bisa dihapus." data-confirm-ok="Ya, hapus" data-confirm-tone="danger" class="row-btn is-danger">Hapus</button>
                        </form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-12 text-center text-stone-500">Belum ada supplier.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $suppliers->links() }}</div>
@endsection
