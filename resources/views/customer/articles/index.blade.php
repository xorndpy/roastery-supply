@extends('layouts.insight')

@section('title', ($category ? $category . ' · ' : '') . 'Insight')

@section('content')
@php $filtering = $search !== '' || $category; @endphp

{{-- ============ SLIDER ARTIKEL PILIHAN (manual, tanpa autoplay) ============ --}}
@if($slides->isNotEmpty())
    <section x-data="{ i: 0, n: {{ $slides->count() }}, go(d) { this.i = (this.i + d + this.n) % this.n } }"
             @keydown.left.prevent="go(-1)" @keydown.right.prevent="go(1)"
             class="relative overflow-hidden bg-[#1a0f0a]" aria-roledescription="carousel" aria-label="Artikel pilihan" tabindex="0">
        <div class="relative h-[360px] sm:h-[440px] lg:h-[500px]">
            @foreach($slides as $k => $s)
                <article x-show="i === {{ $k }}" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         class="absolute inset-0" role="group" aria-roledescription="slide" aria-label="{{ $k + 1 }} dari {{ $slides->count() }}" @if($k) x-cloak @endif>
                    <img src="{{ $s->cover_url }}" alt="" class="absolute inset-0 h-full w-full object-cover" @if($k) loading="lazy" @endif>
                    <div class="absolute inset-0 bg-gradient-to-t from-[#1a0f0a]/90 via-[#1a0f0a]/45 to-[#1a0f0a]/20"></div>
                    <div class="container-x relative flex h-full flex-col justify-end pb-14">
                        <span class="w-fit rounded-full bg-orange-500 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-white">{{ $s->category }}</span>
                        <h2 class="heading mt-4 max-w-4xl text-3xl leading-tight text-white [text-wrap:balance] sm:text-4xl lg:text-[2.75rem]">
                            <a href="{{ route('articles.show', $s->slug) }}" class="after:absolute after:inset-0 focus:outline-none focus-visible:underline">{{ $s->title }}</a>
                        </h2>
                    </div>
                </article>
            @endforeach
        </div>
        @if($slides->count() > 1)
            <button type="button" @click="go(-1)" class="absolute left-3 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-stone-900 shadow transition hover:bg-white sm:left-6" aria-label="Artikel sebelumnya">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
            </button>
            <button type="button" @click="go(1)" class="absolute right-3 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-stone-900 shadow transition hover:bg-white sm:right-6" aria-label="Artikel berikutnya">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            </button>
            <div class="absolute inset-x-0 bottom-5 z-10 flex justify-center gap-2" role="tablist" aria-label="Pilih artikel">
                @foreach($slides as $k => $s)
                    <button type="button" @click="i = {{ $k }}" role="tab" :aria-selected="(i === {{ $k }}).toString()" aria-label="Artikel {{ $k + 1 }}"
                            class="h-2.5 rounded-full transition-all" :class="i === {{ $k }} ? 'w-8 bg-white' : 'w-2.5 bg-white/55 hover:bg-white/80'"></button>
                @endforeach
            </div>
        @endif
    </section>
@endif

{{-- ============ DAFTAR ARTIKEL ============ --}}
<section class="container-x py-14 sm:py-16" aria-labelledby="list-title">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 id="list-title" class="heading text-3xl sm:text-[2.25rem]">
                @if($search !== '') Hasil untuk &ldquo;{{ $search }}&rdquo;
                @elseif($category) {{ $category }}
                @else Artikel Terbaru @endif
            </h1>
            @if($articles->total())<p class="mt-1 text-sm text-stone-500">{{ $articles->total() }} artikel</p>@endif
        </div>
        @if($filtering)<a href="{{ route('articles.index') }}" class="text-sm font-medium text-orange-700 hover:underline">Hapus filter ×</a>@endif
    </div>

    @if($articles->isEmpty())
        <div class="mt-10 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <p class="heading text-xl">{{ $filtering ? 'Belum ada artikel yang cocok.' : 'Artikel akan segera hadir.' }}</p>
            <p class="mt-2 text-sm text-stone-500">{{ $filtering ? 'Coba kata kunci atau kategori lain.' : 'Kami sedang menyiapkan konten pertama.' }}</p>
        </div>
    @else
        <div class="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($articles as $a)<x-article-card :article="$a" />@endforeach
        </div>
        <div class="mt-10">{{ $articles->links() }}</div>
    @endif
</section>
@endsection
