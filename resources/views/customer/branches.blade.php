@extends('layouts.app')
@section('title', 'Cabang Kami')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">
    <h1 class="text-4xl font-bold mb-6">Cabang Kami</h1>
    @forelse($branches as $branch)
        <div class="mb-4 p-4 border border-stone-200 rounded-lg">
            <h3 class="font-semibold">{{ $branch->name }}</h3>
            <p class="text-stone-600 text-sm">{{ $branch->address }}</p>
        </div>
    @empty
        <p class="text-stone-500">Belum ada cabang.</p>
    @endforelse
</div>
@endsection