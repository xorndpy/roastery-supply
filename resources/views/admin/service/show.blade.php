@extends('layouts.admin')

@section('title', 'Tiket ' . $ticket->ticket_number)

@php $next = \App\Models\ServiceTicket::FLOW[$ticket->status] ?? []; $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp

@section('content')
<div class="mb-6"><a href="{{ route('admin.service.index') }}" class="text-sm text-stone-500 hover:text-stone-700">Kembali ke Tiket Servis</a></div>

@if(session('success'))<div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
@if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-lg border border-stone-200 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h1 class="font-mono text-xl font-bold text-stone-900">{{ $ticket->ticket_number }}</h1><p class="text-sm text-stone-600">{{ $ticket->product_name }}@if($ticket->serial_number) · SN {{ $ticket->serial_number }}@endif</p></div>
                <div class="text-right"><span class="rounded-full bg-orange-100 px-3 py-1 text-sm text-orange-800">{{ $ticket->statusLabel() }}</span><p class="mt-1 text-xs {{ $ticket->in_warranty ? 'text-green-700' : 'text-stone-500' }}">{{ $ticket->in_warranty ? 'MASIH GARANSI (gratis)' : 'Di luar garansi (berbayar)' }}</p></div>
            </div>
            <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                <div><dt class="text-stone-500">Pelanggan</dt><dd>{{ $ticket->user->name ?? '-' }} · {{ $ticket->contact_phone }}</dd></div>
                <div><dt class="text-stone-500">Jenis masalah</dt><dd>{{ $ticket->issueLabel() }}</dd></div>
                @if($ticket->order)<div><dt class="text-stone-500">Pesanan asal</dt><dd><a class="underline" href="{{ route('admin.orders.show', $ticket->order->id) }}">{{ $ticket->order->order_number }}</a></dd></div>@endif
                @if($ticket->warranty)<div><dt class="text-stone-500">Garansi</dt><dd>sampai {{ $ticket->warranty->expires_at->format('d M Y') }}</dd></div>@endif
            </dl>
            <p class="mt-4 whitespace-pre-line text-sm text-stone-800">{{ $ticket->description }}</p>
            @if($ticket->photos)<div class="mt-3 flex gap-3">@foreach($ticket->photos as $i => $p)<a href="{{ route('admin.service.photo', [$ticket->id, $i + 1]) }}" target="_blank" rel="noopener" class="text-sm underline">Foto {{ $i + 1 }}</a>@endforeach</div>@endif
        </div>

        <div class="rounded-lg border border-stone-200 bg-white p-5">
            <h3 class="mb-4 font-semibold">Riwayat</h3>
            <ol class="relative ml-2 space-y-4 border-l border-stone-200">
                @foreach($ticket->events->reverse() as $e)
                    <li class="ml-5"><span class="absolute -left-[5px] mt-1.5 h-2.5 w-2.5 rounded-full {{ $loop->first ? 'bg-orange-500 ring-4 ring-orange-100' : 'bg-stone-400' }}"></span>
                        <div class="flex flex-wrap justify-between gap-x-3"><p class="text-sm font-medium">{{ \App\Models\ServiceTicket::STATUSES[$e->status] ?? $e->status }} <span class="font-normal text-stone-500">oleh {{ $e->actor->name ?? 'sistem' }}</span></p><p class="text-xs text-stone-500">{{ wib($e->created_at, 'd M Y, H:i') }}</p></div>
                        @if($e->note)<p class="text-xs text-stone-600">{{ $e->note }}</p>@endif</li>
                @endforeach
            </ol>
        </div>
    </div>

    <div class="space-y-6">
        @can('manage_service')
        <form method="POST" action="{{ route('admin.service.update', $ticket->id) }}" class="space-y-4 rounded-lg border border-stone-200 bg-white p-5">
            @csrf @method('PUT')
            <h3 class="font-semibold">Perbarui tiket</h3>
            <div>
                <label class="mb-1 block text-xs text-stone-500">Status</label>
                <select name="status" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    <option value="{{ $ticket->status }}">{{ $ticket->statusLabel() }} (sekarang)</option>
                    @foreach($next as $s)<option value="{{ $s }}">{{ \App\Models\ServiceTicket::STATUSES[$s] }}</option>@endforeach
                </select>
            </div>
            <div><label class="mb-1 block text-xs text-stone-500">Catatan untuk pelanggan</label><textarea name="note" rows="2" maxlength="1000" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm"></textarea></div>
            @unless($ticket->in_warranty)
                <div><label class="mb-1 block text-xs text-stone-500">Estimasi biaya (Rp), untuk "Menunggu persetujuan"</label><input type="number" name="estimate_cost" min="0" step="1" value="{{ old('estimate_cost', $ticket->estimate_cost ? (int) $ticket->estimate_cost : '') }}" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
                <div><label class="mb-1 block text-xs text-stone-500">Biaya akhir (Rp), untuk "Selesai"</label><input type="number" name="final_cost" min="0" step="1" value="{{ old('final_cost', (float) $ticket->final_cost ? (int) $ticket->final_cost : '') }}" class="w-full rounded-lg border border-stone-300 px-3 py-2"></div>
            @endunless
            <div><label class="mb-1 block text-xs text-stone-500">Catatan internal (tidak terlihat pelanggan)</label><textarea name="admin_note" rows="2" maxlength="2000" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('admin_note', $ticket->admin_note) }}</textarea></div>
            <button class="w-full rounded-lg bg-orange-500 py-2.5 font-medium text-white hover:bg-orange-600">Simpan</button>
        </form>

        @if($ticket->status === 'selesai' && ! $ticket->in_warranty && (float) $ticket->final_cost > 0)
            <div class="rounded-lg border border-stone-200 bg-white p-5 text-sm">
                <p>Biaya servis <strong>{{ $rp($ticket->final_cost) }}</strong></p>
                @if($ticket->paid_at)<p class="mt-1 text-green-700">Dibayar {{ wib($ticket->paid_at, 'd M Y, H:i') }}</p>
                @else<form method="POST" action="{{ route('admin.service.pay', $ticket->id) }}" class="mt-3">@csrf<button class="w-full rounded-lg bg-stone-900 py-2 text-white">Tandai sudah dibayar</button></form>@endif
            </div>
        @endif
        @endcan
    </div>
</div>
@endsection
