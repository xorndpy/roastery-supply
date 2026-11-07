@props(['article'])
<a href="{{ route('articles.show', $article->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-xl border border-stone-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-stone-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
    <div class="relative aspect-[16/9] overflow-hidden bg-[#2b1a10]">
        @if($article->cover_url)
            <img src="{{ $article->cover_url }}" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-[#2b1a10] to-[#4a2f21]"></div>
            <div class="auth-beans absolute inset-0"></div>
        @endif
        @if($article->video_embed)
            <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-[#1a0f0a]/75 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur-sm">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> Video
            </span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-5">
        <span class="text-[11px] font-semibold uppercase tracking-[0.12em] text-orange-600">{{ $article->category }}</span>
        <h3 class="heading mt-2 line-clamp-2 text-[19px] leading-snug text-stone-900">{{ $article->title }}</h3>
        <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-stone-600">{{ $article->excerpt_text }}</p>
        <p class="mt-auto pt-4 text-xs text-stone-500">{{ $article->reading_minutes }} menit baca · {{ $article->published_at?->locale('id')->translatedFormat('F Y') }}</p>
    </div>
</a>
