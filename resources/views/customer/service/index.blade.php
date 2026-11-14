@extends('layouts.shop')

@section('title', 'Garansi & Servis')

@section('content')
<x-page-header plain title="Garansi & Servis" :crumbs="[['Beranda', route('home')], ['Garansi & Servis']]" />

@php
    $badge = ['baru' => 'bg-amber-100 text-amber-800', 'diterima' => 'bg-orange-100 text-orange-800', 'diperiksa' => 'bg-orange-200 text-orange-900', 'menunggu_persetujuan' => 'bg-yellow-100 text-yellow-800', 'dikerjakan' => 'bg-stone-200 text-stone-800', 'selesai' => 'bg-green-100 text-green-800', 'ditolak' => 'bg-red-100 text-red-800', 'dibatalkan' => 'bg-stone-100 text-stone-600'];
@endphp

<div class="container-x py-10 space-y-10">
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif

    <section>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-stone-900">Garansi produk saya</h2>
            <a href="{{ route('customer.service.create') }}" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600">Ajukan servis</a>
        </div>

        @forelse($warranties as $w)
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-stone-200 bg-white p-4">
                <div>
                    <p class="font-medium text-stone-900">{{ $w->product_name }}</p>
                    <p class="text-xs text-stone-600">
                        @if($w->serial_number)SN {{ $w->serial_number }} · @endif
                        Berlaku {{ $w->starts_at->format('d M Y') }} - {{ $w->expires_at->format('d M Y') }}
                        @if($w->isActive()) · sisa {{ $w->daysLeft() }} hari @endif
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="rounded-full px-2.5 py-0.5 text-xs {{ $w->isActive() ? 'bg-green-100 text-green-800' : 'bg-stone-100 text-stone-600' }}">{{ $w->label() }}</span>
                    @if($w->status === 'active')<a href="{{ route('customer.service.create', ['warranty' => $w->id]) }}" class="text-sm text-orange-700 underline">Ajukan servis</a>@endif
                </div>
            </div>
        @empty
            <p class="rounded-lg border border-dashed border-stone-300 p-6 text-sm text-stone-600">Belum ada garansi. Garansi otomatis aktif setelah pesananmu selesai (konfirmasi "Pesanan diterima").</p>
        @endforelse
    </section>

    <section>
        <h2 class="mb-4 text-lg font-semibold text-stone-900">Tiket servis</h2>
        @forelse($tickets as $t)
            <a href="{{ route('customer.service.show', $t->ticket_number) }}" class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-stone-200 bg-white p-4 hover:border-orange-300">
                <div>
                    <p class="font-mono text-sm text-stone-900">{{ $t->ticket_number }}</p>
                    <p class="text-xs text-stone-600">{{ $t->product_name }} · {{ $t->issueLabel() }} · {{ wib($t->created_at, 'd M Y') }}</p>
                </div>
                <span class="rounded-full px-2.5 py-0.5 text-xs {{ $badge[$t->status] ?? 'bg-stone-100' }}">{{ $t->statusLabel() }}</span>
            </a>
        @empty
            <p class="rounded-lg border border-dashed border-stone-300 p-6 text-sm text-stone-600">Belum ada tiket servis.</p>
        @endforelse
    </section>
</div>
@endsection
