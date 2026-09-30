@extends('layouts.app')
@section('title', 'FAQ')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-16">
    <h1 class="text-4xl font-bold mb-6">FAQ</h1>
    @forelse($faqs as $faq)
        <div class="mb-4 border-b border-stone-200 pb-4">
            <h3 class="font-semibold">{{ $faq->question }}</h3>
            <p class="text-stone-600 mt-2">{{ $faq->answer }}</p>
        </div>
    @empty
        <p class="text-stone-500">Belum ada FAQ.</p>
    @endforelse
</div>
@endsection