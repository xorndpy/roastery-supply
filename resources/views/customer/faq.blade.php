@extends('layouts.app')

@section('title', 'FAQ')

@section('content')
<x-page-header title="Pertanyaan Umum" :crumbs="[['Beranda', route('home')], ['Pertanyaan Umum']]" />

<div class="container-x py-10">
    @forelse($faqs as $category => $items)
        <section class="grid gap-4 border-t border-stone-200 py-8 first:border-t-0 first:pt-0 md:grid-cols-[240px_1fr] md:gap-10">
            <h2 class="heading text-xl">{{ $category }}</h2>
            <div class="divide-y divide-stone-200">
                @foreach($items as $faq)
                    <details class="group py-4 first:pt-0">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium [&::-webkit-details-marker]:hidden">
                            {{ $faq->question }}
                            <span class="text-xl leading-none text-orange-500 transition group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-stone-600">{!! nl2br(e($faq->answer)) !!}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-stone-500">Belum ada pertanyaan umum.</p>
    @endforelse

    <div class="mt-6 flex flex-col items-start justify-between gap-5 rounded-2xl bg-brand p-8 text-white sm:flex-row sm:items-center">
        <div>
            <h2 class="heading text-2xl">Belum menemukan jawaban?</h2>
            <p class="mt-1 text-sm text-white/75">Tim kami siap membantu lewat form kontak atau WhatsApp.</p>
        </div>
        <a href="{{ route('contact.index') }}" class="btn bg-white text-brand hover:bg-orange-100">Hubungi Kami</a>
    </div>
</div>
@endsection
