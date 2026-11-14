@extends('layouts.admin')

@section('title', 'Garansi')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Garansi</h1>
    <p class="mt-1 text-sm text-stone-500">Dibuat otomatis per unit saat pesanan selesai. Isi nomor seri unit agar pelanggan dan teknisi mudah mencocokkan.</p>
</div>

@if(session('success'))<div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="GET" class="mb-6 flex flex-wrap gap-3 rounded-lg border border-stone-200 bg-white p-4">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor seri, produk, pelanggan, no. pesanan" class="min-w-[220px] flex-1 rounded-lg border border-stone-300 px-3 py-2">
    <select name="state" class="rounded-lg border border-stone-300 px-3 py-2">
        <option value="">Semua</option>
        @foreach(['active' => 'Aktif', 'expired' => 'Kedaluwarsa', 'void' => 'Dibatalkan', 'noserial' => 'Belum ada nomor seri'] as $k => $l)<option value="{{ $k }}" @selected(request('state') === $k)>{{ $l }}</option>@endforeach
    </select>
    <button class="rounded-lg bg-stone-900 px-5 py-2 text-white">Cari</button>
</form>

<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500"><tr><th class="px-4 py-3">Produk</th><th class="px-4 py-3">Pemilik</th><th class="px-4 py-3">Nomor seri</th><th class="px-4 py-3">Berlaku</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($warranties as $w)
            <tr>
                <td class="px-4 py-3">{{ $w->product_name }}<div class="text-xs text-stone-500">{{ $w->order->order_number ?? '' }}</div></td>
                <td class="px-4 py-3">{{ $w->user->name ?? '-' }}</td>
                <td class="px-4 py-3">
                    @can('manage_warranties')
                        <form method="POST" action="{{ route('admin.warranties.update', $w->id) }}" class="flex gap-2">@csrf @method('PUT')
                            <input type="text" name="serial_number" value="{{ $w->serial_number }}" maxlength="60" placeholder="belum diisi" class="w-40 rounded-lg border border-stone-300 px-2 py-1 text-xs">
                            <button class="text-xs underline">Simpan</button>
                        </form>
                    @else {{ $w->serial_number ?? '-' }} @endcan
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-stone-600">{{ $w->starts_at->format('d M Y') }} - {{ $w->expires_at->format('d M Y') }}</td>
                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-0.5 text-xs {{ $w->isActive() ? 'bg-green-100 text-green-800' : ($w->status === 'void' ? 'bg-red-100 text-red-800' : 'bg-stone-100 text-stone-600') }}">{{ $w->label() }}</span>@if($w->void_reason)<div class="text-xs text-stone-500">{{ $w->void_reason }}</div>@endif</td>
                <td class="px-4 py-3 text-right">
                    @can('manage_warranties')@if($w->status === 'active')
                        <form method="POST" action="{{ route('admin.warranties.void', $w->id) }}" class="inline-flex gap-2">@csrf<input type="text" name="void_reason" required minlength="5" placeholder="Alasan" class="w-28 rounded-lg border border-stone-300 px-2 py-1 text-xs"><button class="text-xs text-red-600 underline">Batalkan</button></form>
                    @endif @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada garansi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $warranties->links() }}</div>
@endsection
