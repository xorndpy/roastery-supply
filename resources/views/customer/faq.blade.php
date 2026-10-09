@extends('layouts.app')

@section('title', 'FAQ')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-16">
    <h1 class="text-4xl font-bold mb-8">Pertanyaan Umum</h1>

    @forelse($faqs as $category => $items)
        <section class="mb-10">
            <h2 class="text-sm uppercase tracking-wider text-stone-500 mb-3">{{ $category }}</h2>
            <div class="bg-white border border-stone-200 rounded-lg divide-y divide-stone-100">
                @foreach($items as $faq)
                    <details class="group p-5">
                        <summary class="cursor-pointer font-medium list-none flex justify-between gap-4">
                            {{ $faq->question }}
                            <span class="text-stone-400 group-open:rotate-45 transition" aria-hidden="true">+</span>
                        </summary>
                        <p class="text-stone-600 mt-3 leading-relaxed">{!! nl2br(e($faq->answer)) !!}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-stone-500">Belum ada pertanyaan umum.</p>
    @endforelse

    <p class="text-sm text-stone-500">Belum terjawab? <a href="{{ route('contact.index') }}" class="text-orange-600 hover:underline">Hubungi kami</a>.</p>
</div>
@endsection
