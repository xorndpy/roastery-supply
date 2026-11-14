@extends('layouts.admin')

@section('title', 'Tutup Buku')

@php $rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.'); @endphp

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Tutup Buku</h1>
    <p class="text-sm text-stone-500 mt-1">Periode yang ditutup terkunci (biaya operasional tidak bisa diubah) dan angkanya disimpan. Kalau data berubah setelahnya, selisihnya terlihat di sini.</p>
</div>

@if(session('success'))<div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>@endif
@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@can('close_finance')
<form method="POST" action="{{ route('admin.finance.closings.store') }}" class="bg-white p-5 rounded-lg border border-stone-200 mb-8 grid md:grid-cols-4 gap-4 items-end">
    @csrf
    <div><label class="block text-xs text-stone-500 mb-1">Dari (harus berurutan)</label><input type="date" name="period_start" value="{{ old('period_start', $suggestedStart) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <div><label class="block text-xs text-stone-500 mb-1">Sampai (paling lambat kemarin)</label><input type="date" name="period_end" value="{{ old('period_end', min($yesterday, \Carbon\Carbon::parse($suggestedStart)->endOfMonth()->toDateString())) }}" max="{{ $yesterday }}" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <div><label class="block text-xs text-stone-500 mb-1">Catatan</label><input type="text" name="note" value="{{ old('note') }}" maxlength="500" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <button class="bg-stone-900 text-white px-5 py-2.5 rounded-lg">Tutup periode</button>
</form>
@endcan

<div class="bg-white rounded-lg border border-stone-200 overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-stone-500">
            <tr><th class="px-4 py-3">Periode</th><th class="px-4 py-3 text-right">Pendapatan bersih</th><th class="px-4 py-3 text-right">Laba bersih (saat ditutup)</th><th class="px-4 py-3 text-right">Selisih sekarang</th><th class="px-4 py-3">Ditutup oleh</th><th class="px-4 py-3"></th></tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($rows as [$c, $drift])
            <tr>
                <td class="px-4 py-3 whitespace-nowrap"><a class="hover:underline" href="{{ route('admin.finance.profit', ['from' => $c->period_start->toDateString(), 'to' => $c->period_end->toDateString()]) }}">{{ $c->period_start->format('d M Y') }} - {{ $c->period_end->format('d M Y') }}</a>@if($c->note)<div class="text-xs text-stone-500">{{ $c->note }}</div>@endif</td>
                <td class="px-4 py-3 text-right">{{ $rp($c->snapshot['revenue'] ?? 0) }}</td>
                <td class="px-4 py-3 text-right">{{ $rp($c->snapshot['net_profit'] ?? 0) }}</td>
                <td class="px-4 py-3 text-right">
                    @if(abs($drift['net_profit']) < 1 && abs($drift['revenue']) < 1)<span class="text-xs rounded-full bg-green-50 text-green-700 px-2 py-0.5">Tidak ada selisih</span>
                    @else<span class="text-xs rounded-full bg-amber-100 text-amber-800 px-2 py-0.5" title="Laba bersih sekarang {{ $rp($drift['now_net_profit']) }}">Laba {{ $drift['net_profit'] > 0 ? '+' : '' }}{{ $rp($drift['net_profit']) }}</span>@endif
                </td>
                <td class="px-4 py-3 text-stone-600">{{ $c->closer->name ?? '-' }}<div class="text-xs">{{ wib($c->closed_at, 'd M Y, H:i') }}</div></td>
                <td class="px-4 py-3 text-right">
                    @can('close_finance')
                        @if($loop->first)
                            <form method="POST" action="{{ route('admin.finance.closings.reopen', $c->id) }}" class="inline-flex gap-2 items-center">@csrf @method('DELETE')
                                <input type="text" name="reason" required minlength="5" placeholder="Alasan membuka kembali" class="border border-stone-300 rounded-lg px-2 py-1 text-xs w-44">
                                <button class="text-red-600 hover:underline text-xs">Buka kembali</button>
                            </form>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Belum ada penutupan buku.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
