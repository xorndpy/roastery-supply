@extends('layouts.app')

@section('title', 'Roastery Supply — Mesin Kopi Profesional')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <h1 class="text-5xl font-bold mb-4">Mesin Kopi Profesional</h1>
    <p class="text-lg text-stone-600 mb-8">Untuk kedai kopi yang serius.</p>
    <a href="{{ route('products.index') }}" class="bg-orange-500 text-white px-6 py-3 rounded-lg hover:bg-orange-600">
        Lihat Katalog
    </a>
</div>
@endsection