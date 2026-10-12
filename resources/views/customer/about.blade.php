@extends('layouts.app')

@section('title', $site['about_title'])

@section('content')
<x-page-header :title="$site['about_title']" :crumbs="[['Beranda', route('home')], [$site['about_title']]]" :bg="$site['about_header_bg'] ?? null" />

<div class="container-x py-12">
    <div class="max-w-2xl space-y-5 text-[17px] leading-relaxed text-stone-600">
        @foreach(preg_split('/\R{2,}/', trim($site['about_body'] ?? '')) as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>

    <div class="mt-14 grid gap-10 md:grid-cols-3">
        @foreach([
            ['01', 'Konsultasi Setup', 'Rekomendasi mesin dan alur kerja sesuai kapasitas dan anggaran.'],
            ['02', 'Instalasi & Training', 'Pemasangan di lokasi dan pelatihan dasar untuk barista Anda.'],
            ['03', 'Servis & Sparepart', 'Perawatan berkala dan suku cadang untuk produk yang kami jual.'],
        ] as [$n, $t, $d])
            <div class="border-t-2 border-brand pt-5">
                <div class="text-[13px] font-semibold text-orange-500">{{ $n }}</div>
                <h3 class="heading mt-1.5 text-lg">{{ $t }}</h3>
                <p class="mt-2 text-sm text-stone-500">{{ $d }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection
