@extends('layouts.shop')

@section('title', 'Bandingkan Produk')

@section('content')
<x-page-header plain title="Bandingkan produk" :crumbs="[['Beranda', route('home')], ['Katalog', route('products.index')], ['Bandingkan']]" />

@php
    $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $differs = fn (array $vals) => count(array_unique(array_map(fn ($v) => mb_strtolower((string) $v), $vals))) > 1;
    $weight = fn ($g) => $g ? ($g >= 1000 ? rtrim(rtrim(number_format($g / 1000, 1, ',', '.'), '0'), ',') . ' kg' : $g . ' g') : '-';
@endphp

<div class="container-x py-6 sm:py-10 pb-28">
@if($products->count() < 2)
    <div class="rounded-xl border border-stone-200 bg-white p-8 text-center">
        <p class="font-medium text-stone-900">Pilih minimal 2 produk untuk dibandingkan.</p>
        <p class="mt-1 text-sm text-stone-500">Centang "Bandingkan" pada kartu produk di katalog (maksimal {{ $limit }} produk).</p>
        <a href="{{ route('products.index') }}" class="btn btn-primary mt-5 inline-flex">Ke katalog</a>
    </div>
@else
    <p class="mb-3 text-sm text-stone-500">{{ $products->count() }} produk dipilih. Baris yang nilainya berbeda diberi latar abu.</p>
    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full min-w-[640px] border-collapse text-sm">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 w-36 bg-white p-3 text-left align-bottom text-xs font-medium uppercase tracking-wide text-stone-500"></th>
                    @foreach($products as $p)
                        @php $img = $p->images->first(); @endphp
                        <th class="min-w-[170px] border-l border-stone-100 p-3 text-center align-top font-medium">
                            <a href="{{ route('products.show', $p->slug) }}" class="block">
                                <div class="mx-auto mb-2 flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg bg-stone-50">
                                    @if($img)<img src="{{ thumb_url($img->image) }}" alt="{{ $p->name }}" class="h-full w-full object-contain" loading="lazy">@else<span class="text-xs text-stone-400">Foto</span>@endif
                                </div>
                                @if($p->brand)<span class="block text-xs text-stone-500">{{ $p->brand->name }}</span>@endif
                                <span class="block leading-snug text-stone-900 hover:underline">{{ $p->name }}</span>
                            </a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $fixed = [
                        'Harga' => fn ($p) => $rp(($fi = $p->flashItem()) ? $fi->sale_price : $p->price),
                        'Merek' => fn ($p) => $p->brand->name ?? '-',
                        'Kategori' => fn ($p) => $p->category->name ?? '-',
                        'Stok' => fn ($p) => $p->stock <= 0 ? 'Habis' : 'Tersedia',
                        'Garansi' => fn ($p) => $p->warranty_months ? $p->warranty_months . ' bulan' : '-',
                        'Berat' => fn ($p) => $weight($p->weight),
                    ];
                @endphp
                @foreach($fixed as $label => $get)
                    @php $vals = $products->map(fn ($p) => $get($p))->all(); @endphp
                    <tr class="{{ $differs($vals) ? 'bg-stone-50' : '' }}">
                        <td class="sticky left-0 z-10 border-t border-stone-100 p-3 font-medium {{ $differs($vals) ? 'bg-stone-50' : 'bg-white' }}">{{ $label }}</td>
                        @foreach($vals as $v)<td class="border-l border-t border-stone-100 p-3 text-center {{ $label === 'Harga' ? 'font-semibold' : '' }}">{{ $v }}</td>@endforeach
                    </tr>
                @endforeach
                @foreach($specLabels as $k => $label)
                    @php $vals = $products->map(fn ($p) => $specValues[$k][$p->id] ?? '-')->all(); @endphp
                    <tr class="{{ $differs($vals) ? 'bg-stone-50' : '' }}">
                        <td class="sticky left-0 z-10 border-t border-stone-100 p-3 font-medium {{ $differs($vals) ? 'bg-stone-50' : 'bg-white' }}">{{ $label }}</td>
                        @foreach($vals as $v)<td class="border-l border-t border-stone-100 p-3 text-center">{{ $v }}</td>@endforeach
                    </tr>
                @endforeach
                <tr>
                    <td class="sticky left-0 z-10 border-t border-stone-100 bg-white p-3"></td>
                    @foreach($products as $p)
                        <td class="border-l border-t border-stone-100 p-3 text-center">
                            <form method="POST" action="{{ route('customer.cart.store') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $p->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" @disabled($p->stock <= 0) class="btn btn-outline btn-sm w-full">{{ $p->stock <= 0 ? 'Stok habis' : 'Tambah ke keranjang' }}</button>
                            </form>
                            <form method="POST" action="{{ route('compare.toggle', $p->slug) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="text-xs text-stone-500 underline underline-offset-2 hover:text-red-700">Hapus dari perbandingan</button>
                            </form>
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-stone-500">Spesifikasi diisi oleh admin di data produk. Tanda "-" berarti belum ada datanya.</p>
@endif
</div>
@endsection
