@extends('layouts.shop')

@section('title', 'Akun Saya')

@section('content')
@php
    $cover = $user->cover_url;
    $statusLabels = ['menunggu_bayar' => 'Menunggu Bayar', 'dikonfirmasi' => 'Dikonfirmasi', 'diproses' => 'Diproses', 'dikirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal'];
    $statusBadge = ['menunggu_bayar' => 'bg-amber-100 text-amber-800', 'dikonfirmasi' => 'bg-copper-100 text-copper-800', 'diproses' => 'bg-copper-200 text-copper-900', 'dikirim' => 'bg-stone-200 text-stone-800', 'selesai' => 'bg-green-100 text-green-800', 'batal' => 'bg-red-100 text-red-800'];
@endphp

{{-- Header: foto profil + sapaan. Foto sampul (opsional) jadi latar dengan lapisan gelap agar teks tetap terbaca. --}}
<div class="relative overflow-hidden border-b border-stone-200 {{ $cover ? 'text-white' : 'bg-stone-100' }}">
    @if($cover)
        <img src="{{ $cover }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-stone-900/65"></div>
    @endif
    <div class="container-x relative flex items-center gap-3.5 py-5 sm:gap-5 sm:py-9">
        <x-avatar :user="$user" :size="64" :ring="true" class="sm:!h-20 sm:!w-20" />
        <div class="min-w-0">
            <div class="eyebrow {{ $cover ? '!text-white/80' : '' }}">Akun Saya</div>
            <h1 class="heading mt-0.5 truncate text-2xl sm:mt-1 sm:text-4xl">Halo, {{ \Illuminate\Support\Str::words($user->name, 1, '') }}</h1>
            <p class="mt-1 text-sm {{ $cover ? 'text-white/85' : 'text-stone-600' }}">
                @if($stats['total'] > 0){{ $stats['total'] }} pesanan · {{ $stats['done'] }} selesai @else Belum ada pesanan @endif
                <span class="max-sm:hidden">·</span> <a href="{{ route('customer.profile.edit') }}" class="underline underline-offset-4 max-sm:mt-0.5 max-sm:block">Ubah foto &amp; profil</a>
            </p>
        </div>
    </div>
</div>

<div class="container-x space-y-5 pb-28 pt-5 sm:space-y-8 sm:pt-10 lg:pb-12">

    {{-- 1. Tindakan berikutnya --}}
    @if($action)
        @php $o = $action['order']; @endphp
        <section aria-labelledby="aksi-title" class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-xl border border-stone-300 bg-white p-4 shadow-sm sm:p-5">
            <div class="min-w-0 flex-1 basis-60">
                @if($action['type'] === 'pay')
                    <h2 id="aksi-title" class="font-semibold">{{ $action['reviewing'] ? 'Pembayaran sedang diperiksa' : 'Menunggu pembayaran' }}</h2>
                    <p class="mt-0.5 text-sm text-stone-600">
                        {{ $o->order_number }} · Rp {{ number_format($o->total, 0, ',', '.') }}
                        @unless($action['reviewing']) · bayar sebelum {{ $action['deadline']->translatedFormat('d M Y, H:i') }} @endunless
                    </p>
                    @if($action['others'] > 0)
                        <p class="mt-0.5 text-xs text-stone-600">Ada {{ $action['others'] }} pesanan lain yang juga menunggu pembayaran. <a href="{{ route('customer.orders.index') }}" class="underline underline-offset-4">Lihat semua</a></p>
                    @endif
                @else
                    <h2 id="aksi-title" class="font-semibold">Pesanan sedang dikirim</h2>
                    <p class="mt-0.5 text-sm text-stone-600">{{ $o->order_number }}@if($o->shipping_method) · {{ $o->shipping_method }}@endif</p>
                @endif
            </div>
            <a href="{{ route('customer.orders.show', $o->order_number) }}" class="btn btn-primary min-h-11 max-sm:w-full">
                {{ $action['type'] === 'pay' ? ($action['reviewing'] ? 'Lihat pesanan' : 'Bayar sekarang') : 'Lacak pesanan' }}
            </a>
        </section>
    @endif

    {{-- 2. Voucher untuk kamu --}}
    @if($vouchers->isNotEmpty())
        <section aria-labelledby="voucher-title">
            <div class="mb-3 flex items-end justify-between gap-3">
                <h2 id="voucher-title" class="heading text-lg sm:text-xl">
                    {{ $isNewMember && $vouchers->contains('is_new_member', true) ? 'Selamat datang! Ini voucher member baru untukmu' : 'Voucher untuk kamu' }}
                </h2>
            </div>
            <ul class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-3">
                @foreach($vouchers as $v)
                    <li x-data="{ done: false }" class="flex flex-col rounded-xl border border-dashed border-stone-400 bg-white p-4 max-sm:w-[82%] max-sm:max-w-[320px] max-sm:shrink-0 max-sm:snap-start">
                        <div class="flex items-center gap-2 text-xs text-stone-600">
                            <x-ui-icon name="ticket" class="h-4 w-4 text-copper-600" />
                            {{ $v['is_new_member'] ? 'Member baru' : 'Semua pelanggan' }}
                        </div>
                        <div class="mt-1 text-lg font-semibold">{{ $v['benefit'] }}</div>
                        @if($v['description'])<p class="text-sm text-stone-700">{{ $v['description'] }}</p>@endif
                        @if($v['terms'])<p class="mt-1 text-xs leading-relaxed text-stone-600">{{ implode(' · ', $v['terms']) }}</p>@endif
                        <p class="mt-1 text-xs text-stone-600">
                            @if($v['valid_until']) Berlaku sampai {{ $v['valid_until']->translatedFormat('d M Y') }} @else Tanpa batas waktu @endif
                            @if($v['quota_left']) · <strong class="font-semibold text-stone-800">Sisa {{ $v['quota_left'] }} kuota</strong> @endif
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2 pt-1 sm:mt-auto">
                            <code class="rounded bg-stone-100 px-2 py-1 text-sm font-semibold tracking-wide">{{ $v['code'] }}</code>
                            <button type="button" class="min-h-11 rounded-lg border border-stone-300 px-3 text-sm font-medium hover:bg-stone-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                                    @click="navigator.clipboard?.writeText('{{ $v['code'] }}').then(() => { done = true; setTimeout(() => done = false, 2500) })"
                                    aria-label="Salin kode voucher {{ $v['code'] }}">Salin kode</button>
                            <span class="text-xs font-medium text-stone-800" role="status" aria-live="polite"><span x-show="done" x-cloak>Kode disalin</span></span>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-sm text-stone-600">Voucher dipakai di halaman checkout, tinggal pilih atau ketik kodenya.</p>
        </section>
    @endif

    {{-- 3. Pesanan terakhir --}}
    <section aria-labelledby="pesanan-title" class="overflow-hidden rounded-xl border border-stone-200 bg-white">
        <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-6">
            <h2 id="pesanan-title" class="heading min-w-0 text-lg">Pesanan Terakhir</h2>
            <a href="{{ route('customer.orders.index') }}" class="shrink-0 whitespace-nowrap text-sm text-copper-600 underline underline-offset-4 hover:decoration-2">Lihat semua<span class="max-sm:hidden"> pesanan</span></a>
        </div>

        @forelse($recentOrders as $order)
            @php
                $first = $order->items->first();
                $img = $first?->product?->images->first();
                $more = max(0, $order->items->count() - 1);
            @endphp
            <a href="{{ route('customer.orders.show', $order->order_number) }}"
               class="flex items-center gap-3 border-t border-stone-100 px-4 py-3.5 transition hover:bg-stone-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 sm:gap-4 sm:px-6">
                @if($img)
                    <img src="{{ thumb_url($img->image) }}" alt="" loading="lazy" class="h-12 w-12 shrink-0 rounded-lg border border-stone-200 bg-white object-contain p-0.5">
                @else
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-stone-500"><x-ui-icon name="receipt" class="h-5 w-5" /></span>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium">{{ $first?->product_name ?? $order->order_number }}@if($more > 0) <span class="font-normal text-stone-600">dan {{ $more }} produk lain</span>@endif</div>
                    <div class="text-xs text-stone-600">{{ $order->order_number }} · {{ $order->created_at->translatedFormat('d M Y') }}</div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-center sm:gap-4">
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge[$order->status] ?? 'bg-stone-100 text-stone-700' }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
                    <span class="text-sm font-semibold">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </a>
        @empty
            <div class="border-t border-stone-100 px-6 py-8 text-center sm:py-12">
                <p class="text-stone-600">Belum ada pesanan.</p>
                <a href="{{ route('products.index') }}" class="btn btn-primary mt-4">Mulai Belanja</a>
            </div>
        @endforelse
    </section>

    {{-- 4. Pintasan: setiap ikon berbeda supaya tidak membingungkan --}}
    <nav aria-label="Pintasan akun" class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach([
            ['receipt', 'Pesanan Saya', route('customer.orders.index')],
            ['user', 'Profil & Foto', route('customer.profile.edit')],
            ['shield', 'Garansi & Servis', route('customer.service.index')],
            ['chat', 'Bantuan', route('contact.index')],
        ] as [$icon, $label, $url])
            <a href="{{ $url }}" class="group flex min-h-14 items-center gap-2.5 rounded-xl border border-stone-200 bg-white p-3 shadow-sm sm:gap-3 sm:p-3.5 transition hover:-translate-y-0.5 hover:border-copper-500 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 sm:p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-copper-50 text-copper-600 sm:h-10 sm:w-10"><x-ui-icon :name="$icon" class="h-5 w-5" /></span>
                <span class="min-w-0 text-sm font-medium leading-tight">{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</div>
@endsection
