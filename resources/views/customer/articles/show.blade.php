@extends('layouts.insight')

@section('title', $article->title)

@section('content')
<article class="container-x py-10 sm:py-14">
    <div class="mx-auto max-w-3xl">
        <nav class="text-sm text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('articles.index') }}" class="hover:text-stone-900">Insight</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('articles.index', ['kategori' => \Illuminate\Support\Str::slug($article->category)]) }}" class="hover:text-stone-900">{{ $article->category }}</a>
        </nav>

        <h1 class="heading mt-4 text-3xl leading-tight [text-wrap:balance] sm:text-[2.6rem]">{{ $article->title }}</h1>
        @if($article->excerpt)<p class="mt-4 text-lg leading-relaxed text-stone-600">{{ $article->excerpt }}</p>@endif

        <p class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-stone-500">
            @if($article->author_name)<span class="font-medium text-stone-700">{{ $article->author_name }}</span><span aria-hidden="true">·</span>@endif
            <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->locale('id')->translatedFormat('j F Y') }}</time>
            <span aria-hidden="true">·</span><span>{{ $article->reading_minutes }} menit baca</span>
            <span aria-hidden="true">·</span><span>{{ number_format($article->views) }} kali dibaca</span>
        </p>
    </div>

    @if($article->cover_url)
        <img src="{{ $article->cover_url }}" alt="" class="mx-auto mt-8 aspect-[16/9] w-full max-w-5xl rounded-2xl object-cover">
    @endif

    <div class="mx-auto mt-10 max-w-3xl">
        @if($embed = $article->video_embed)
            <div class="mb-10 aspect-video overflow-hidden rounded-2xl bg-black">
                <iframe src="{{ $embed['src'] }}" title="Video: {{ $article->title }}" class="h-full w-full" loading="lazy" allowfullscreen
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        @endif

        <div class="article-body">{!! $article->html !!}</div>

        <div x-data="{ copied: false }" class="mt-12 flex flex-wrap items-center gap-3 border-t border-stone-200 pt-6 text-sm">
            <span class="text-stone-500">Bagikan:</span>
            <a href="https://wa.me/?text={{ urlencode($article->title . ' ' . route('articles.show', $article->slug)) }}" target="_blank" rel="noopener noreferrer" class="rounded-full border border-stone-300 px-4 py-1.5 text-stone-700 transition hover:border-stone-500">WhatsApp</a>
            <button type="button" @click="navigator.clipboard.writeText('{{ route('articles.show', $article->slug) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="rounded-full border border-stone-300 px-4 py-1.5 text-stone-700 transition hover:border-stone-500" x-text="copied ? 'Tautan disalin ✓' : 'Salin tautan'">Salin tautan</button>
        </div>
    </div>
</article>

@if($related->isNotEmpty())
    <section class="border-t border-stone-200 bg-white py-14" aria-labelledby="related-title">
        <div class="container-x">
            <h2 id="related-title" class="heading text-2xl">Baca juga</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach($related as $r)<x-article-card :article="$r" />@endforeach
            </div>
        </div>
    </section>
@endif
@endsection
