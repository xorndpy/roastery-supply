@extends('layouts.admin')

@section('title', 'Biaya Operasional')

@php $cats = \App\Models\Expense::CATEGORIES; @endphp

@section('content')

<div class="flex flex-wrap justify-between items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Biaya Operasional</h1>
        <p class="text-sm text-stone-500 mt-1">Gaji, sewa, listrik, iklan, dan biaya lain yang dikurangkan di laporan laba rugi.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @can('create_expenses')
            <form method="POST" action="{{ route('admin.expenses.copy-recurring') }}">@csrf<button class="border border-stone-300 px-4 py-2.5 rounded-lg text-sm hover:bg-stone-50">Salin biaya berulang bulan lalu</button></form>
            <a href="{{ route('admin.expenses.create') }}" class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-lg font-medium">+ Catat Biaya</a>
        @endcan
    </div>
</div>

@if(session('success'))<div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>@endif
@if($lockedUntil)<div class="mb-4 p-3 rounded-lg border border-stone-200 bg-white text-sm text-stone-600">Buku ditutup sampai <strong class="text-stone-900">{{ \Carbon\Carbon::parse($lockedUntil)->format('d M Y') }}</strong>. Biaya sampai tanggal itu terkunci.</div>@endif

<form method="GET" class="bg-white p-4 rounded-lg border border-stone-200 mb-4 flex flex-wrap items-end gap-3">
    <div><label class="block text-xs text-stone-500 mb-1">Dari</label><input type="date" name="from" value="{{ request('from') }}" class="border border-stone-300 rounded-lg px-3 py-2"></div>
    <div><label class="block text-xs text-stone-500 mb-1">Sampai</label><input type="date" name="to" value="{{ request('to') }}" class="border border-stone-300 rounded-lg px-3 py-2"></div>
    <div>
        <label class="block text-xs text-stone-500 mb-1">Kategori</label>
        <select name="category" class="border border-stone-300 rounded-lg px-3 py-2">
            <option value="">Semua</option>
            @foreach($cats as $key => $label)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <div class="flex-1 min-w-[160px]"><label class="block text-xs text-stone-500 mb-1">Cari keterangan</label><input type="text" name="search" value="{{ request('search') }}" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <button class="bg-stone-900 text-white px-5 py-2 rounded-lg">Terapkan</button>
</form>

<div class="mb-4 text-sm text-stone-600">Total sesuai filter: <strong class="text-stone-900">Rp {{ number_format($total, 0, ',', '.') }}</strong></div>

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Kategori</th><th class="px-4 py-3">Keterangan</th><th class="px-4 py-3 text-right">Jumlah</th><th class="px-4 py-3">Dicatat oleh</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($expenses as $e)
            <tr>
                <td class="px-4 py-3 whitespace-nowrap">{{ $e->expense_date->format('d M Y') }}</td>
                <td class="px-4 py-3"><span class="rounded-full bg-stone-100 text-stone-700 px-2.5 py-0.5 text-xs">{{ $e->categoryLabel() }}</span></td>
                <td class="px-4 py-3">{{ $e->description }}
                    @if($e->is_recurring)<span class="ml-1 text-xs rounded bg-blue-50 text-blue-700 px-1.5 py-0.5">berulang</span>@endif
                    @if($e->attachment)<a href="{{ route('admin.expenses.attachment', $e->id) }}" target="_blank" rel="noopener" class="ml-1 text-xs text-stone-600 underline">lampiran</a>@endif
                    @if($e->note)<div class="text-xs text-stone-500">{{ $e->note }}</div>@endif</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">Rp {{ number_format($e->amount, 0, ',', '.') }}</td>
                <td class="px-4 py-3 text-stone-600">{{ $e->creator->name ?? '-' }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                    @can('edit_expenses')<a href="{{ route('admin.expenses.edit', $e->id) }}" class="text-stone-700 hover:underline">Ubah</a>@endcan
                    @can('delete_expenses')
                        <form method="POST" action="{{ route('admin.expenses.destroy', $e->id) }}" class="inline" data-confirm-title="Hapus biaya ini?" data-confirm-text="{{ $e->description }} (Rp {{ number_format($e->amount, 0, ',', '.') }}) tidak lagi dihitung di laba rugi." data-confirm-ok="Ya, hapus" data-confirm-tone="danger">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada biaya yang dicatat.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $expenses->links() }}</div>
@endsection
