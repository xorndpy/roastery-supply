@extends('layouts.app')

@section('title', 'Cabang Kami')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-16">
    <h1 class="text-4xl font-bold mb-8">Cabang Kami</h1>

    <div class="space-y-6">
    @forelse($branches as $branch)
        @php
            $hasMap = $branch->latitude !== null && $branch->longitude !== null;
            // koordinat berupa angka (divalidasi + cast), aman untuk disisipkan ke URL
            $lat = (float) $branch->latitude; $lng = (float) $branch->longitude;
        @endphp
        <article class="bg-white border border-stone-200 rounded-lg overflow-hidden grid grid-cols-1 {{ $hasMap ? 'md:grid-cols-2' : '' }}">
            <div class="p-6">
                <h2 class="text-xl font-bold">{{ $branch->name }}</h2>
                <p class="text-stone-600 mt-2 whitespace-pre-line">{{ $branch->address }}</p>
                @if($branch->phone)<p class="mt-3 text-sm"><span class="text-stone-500">Telepon:</span> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $branch->phone) }}" class="hover:underline">{{ $branch->phone }}</a></p>@endif
                @if($branch->maps_url || $hasMap)
                    <a href="{{ $branch->maps_url ?: 'https://www.google.com/maps?q=' . $lat . ',' . $lng }}" target="_blank" rel="noopener noreferrer"
                       class="inline-block mt-4 text-sm text-orange-600 hover:underline">Buka di Google Maps →</a>
                @endif
            </div>
            @if($hasMap)
                <iframe title="Peta {{ $branch->name }}" class="w-full h-64 md:h-full min-h-[16rem] border-0" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        src="https://maps.google.com/maps?q={{ $lat }},{{ $lng }}&z=16&output=embed"></iframe>
            @endif
        </article>
    @empty
        <p class="text-stone-500">Informasi cabang belum tersedia.</p>
    @endforelse
    </div>
</div>
@endsection
