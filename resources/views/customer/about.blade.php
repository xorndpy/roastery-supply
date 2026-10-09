@extends('layouts.app')

@section('title', $site['about_title'])

@section('content')
<div class="max-w-3xl mx-auto px-4 py-16">
    <h1 class="text-4xl font-bold mb-6">{{ $site['about_title'] }}</h1>
    <div class="space-y-4 text-stone-700 leading-relaxed">
        @foreach(preg_split('/\R{2,}/', trim($site['about_body'] ?? '')) as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>
</div>
@endsection
