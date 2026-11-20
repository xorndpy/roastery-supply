@extends('layouts.admin')

@section('title', 'Flash Sale')

@php
    $badge = ['running' => ['Berjalan', 'bg-green-100 text-green-800'], 'scheduled' => ['Terjadwal', 'bg-blue-100 text-blue-800'], 'ended' => ['Selesai', 'bg-stone-100 text-stone-600'], 'off' => ['Nonaktif', 'bg-red-100 text-red-700']];
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-stone-900">Flash Sale</h1>
        <p class="mt-1 text-sm text-stone-500">Harga promo berjadwal dengan kuota. Yang tampil di etalase: kampanye berjalan yang paling cepat berakhir.</p>
    </div>
    @can('create_flash_sales')<a href="{{ route('admin.flash-sales.create') }}" class="rounded-lg bg-copper-500 px-5 py-2.5 font-medium text-white hover:bg-copper-600">+ Kampanye Baru</a>@endcan
</div>


<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Kampanye</th><th class="px-4 py-3">Jadwal (WIB)</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Produk</th><th class="px-4 py-3 text-right">Terjual / Kuota</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($sales as $s)
            @php [$label, $cls] = $badge[$s->state()]; $quota = $s->items->sum('quota'); $soldN = $s->items->sum(fn ($i) => $sold[$i->id] ?? 0); @endphp
            <tr>
                <td class="px-4 py-3 font-medium">{{ $s->name }}</td>
                <td class="whitespace-nowrap px-4 py-3 text-stone-600">{{ wib($s->starts_at, 'd M Y, H:i') }}<br>s/d {{ wib($s->ends_at, 'd M Y, H:i') }}</td>
                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs {{ $cls }}">{{ $label }}</span></td>
                <td class="px-4 py-3 text-right">{{ $s->items_count }}</td>
                <td class="px-4 py-3 text-right">{{ $soldN }} / {{ $quota }}</td>
                <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                    @can('edit_flash_sales')<a href="{{ route('admin.flash-sales.edit', $s->id) }}" class="hover:underline">Ubah</a>@endcan
                    @can('delete_flash_sales')
                        <form method="POST" action="{{ route('admin.flash-sales.destroy', $s->id) }}" class="inline" data-confirm-title="Hapus kampanye {{ $s->name }}?" data-confirm-text="Kampanye yang sudah punya penjualan tidak bisa dihapus." data-confirm-ok="Ya, hapus" data-confirm-tone="danger">@csrf @method('DELETE')<button class="row-btn is-danger">Hapus</button></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada kampanye.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sales->links() }}</div>
@endsection
