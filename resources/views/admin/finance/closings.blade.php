@extends('layouts.admin')

@section('title', 'Tutup Buku')

@php $rp = fn ($n) => ($n < 0 ? '-' : '') . 'Rp ' . number_format(abs($n), 0, ',', '.'); @endphp

@section('content')
<x-hpp-warning />

<div class="mb-6">
    <h1 class="text-2xl font-bold text-stone-900">Tutup Buku</h1>
    <p class="text-sm text-stone-500 mt-1">Periode yang ditutup terkunci (biaya operasional tidak bisa diubah) dan angkanya disimpan. Kalau data berubah setelahnya, selisihnya terlihat di sini.</p>
</div>

@if ($errors->any())
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm"><ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@can('close_finance')
<form method="POST" action="{{ route('admin.finance.closings.store') }}" class="bg-white p-5 rounded-lg border border-stone-200 mb-8 grid md:grid-cols-4 gap-4 items-end">
    @csrf
    <div><label class="block text-xs text-stone-500 mb-1">Dari (harus berurutan)</label><input type="date" name="period_start" value="{{ old('period_start', $suggestedStart) }}" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <div><label class="block text-xs text-stone-500 mb-1">Sampai (paling lambat kemarin)</label><input type="date" name="period_end" value="{{ old('period_end', min($yesterday, \Carbon\Carbon::parse($suggestedStart)->endOfMonth()->toDateString())) }}" max="{{ $yesterday }}" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <div><label class="block text-xs text-stone-500 mb-1">Catatan</label><input type="text" name="note" value="{{ old('note') }}" maxlength="500" class="w-full border border-stone-300 rounded-lg px-3 py-2"></div>
    <button type="submit" data-confirm-title="Tutup periode ini?" data-confirm-text="Biaya operasional pada periode itu akan terkunci dan angka laba disimpan. Hanya penutupan terakhir yang bisa dibuka kembali." data-confirm-ok="Ya, tutup periode" data-confirm-cancel="Kembali" class="bg-stone-900 hover:bg-stone-800 text-white px-5 py-2.5 rounded-lg font-medium">Tutup periode</button>
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
                    @if(abs($drift['net_profit']) < 1 && abs($drift['revenue']) < 1)
                        <span class="text-xs text-stone-500">Sesuai</span>
                    @else
                        <span class="whitespace-nowrap text-sm font-medium text-red-700" title="Laba bersih sekarang {{ $rp($drift['now_net_profit']) }}">Selisih {{ $drift['net_profit'] > 0 ? '+' : '' }}{{ $rp($drift['net_profit']) }}</span>
                        <div class="text-xs text-stone-500">data berubah setelah ditutup</div>
                    @endif
                </td>
                <td class="px-4 py-3 text-stone-600">{{ $c->closer->name ?? '-' }}<div class="text-xs">{{ wib($c->closed_at, 'd M Y, H:i') }}</div></td>
                <td class="px-4 py-3 text-right">
                    @can('close_finance')
                        @if($loop->first)
                            <div x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }" @keydown.escape.window="open = false" class="inline-block text-left">
                                <button type="button" @click="open = true" class="row-btn is-danger">Buka kembali</button>
                                <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="reopen-title-{{ $c->id }}">
                                    <div class="absolute inset-0 bg-stone-900/50" @click="open = false"></div>
                                    <form method="POST" action="{{ route('admin.finance.closings.reopen', $c->id) }}" class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl whitespace-normal" data-no-lock>
                                        @csrf @method('DELETE')
                                        <h2 id="reopen-title-{{ $c->id }}" class="text-lg font-bold text-stone-900">Buka kembali periode {{ $c->period_start->format('d M Y') }} - {{ $c->period_end->format('d M Y') }}?</h2>
                                        <p class="mt-2 text-sm text-stone-600">Biaya operasional pada periode ini bisa diubah lagi dan angka laba yang tersimpan dihapus. Tindakan ini tercatat di log aktivitas.</p>
                                        <label for="reason-{{ $c->id }}" class="mt-4 block text-sm font-medium text-stone-800">Alasan membuka kembali</label>
                                        <textarea id="reason-{{ $c->id }}" name="reason" rows="3" required minlength="5" maxlength="500" x-effect="if (open) $nextTick(() => $el.focus())" placeholder="Contoh: salah input biaya sewa bulan Oktober" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"></textarea>
                                        <p class="mt-1 text-xs text-stone-500">Wajib diisi, minimal 5 karakter.</p>
                                        <div class="mt-5 flex justify-end gap-2">
                                            <button type="button" @click="open = false" class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Batal</button>
                                            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Ya, buka kembali</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
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
