{{-- book/NN/NN.html — one compiled chapter page. The chapter title (plain text,
     auto-escaped) followed by each scene's `contents` rendered Markdown → HTML and
     joined by <hr> (no scene titles). Prev/next reading navigation sits at the top
     AND the bottom; at the ends it points back to the TOC.

     $scenesContents is an ordered array of raw Markdown strings. This is the ONE
     place the export renders Markdown to HTML — mirrors the app's render path
     (Str::markdown on Scene.contents in story/index and shared/scenes/show). --}}
@extends('exports.book.layout', ['title' => $chapterTitle])

@section('content')
    <nav class="reading-nav">
        <a href="{{ $prevHref }}" rel="prev">&larr; {{ __('Previous') }}</a>
        <a href="{{ $nextHref }}" rel="next">{{ __('Next') }} &rarr;</a>
    </nav>

    <h1>{{ $chapterTitle }}</h1>

    @foreach ($scenesContents as $index => $contents)
        @if ($index > 0)
            <hr>
        @endif
        {!! Str::markdown($contents ?? '') !!}
    @endforeach

    <nav class="reading-nav">
        <a href="{{ $prevHref }}" rel="prev">&larr; {{ __('Previous') }}</a>
        <a href="{{ $nextHref }}" rel="next">{{ __('Next') }} &rarr;</a>
    </nav>
@endsection
