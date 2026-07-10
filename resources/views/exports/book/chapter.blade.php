{{-- book/NN/NN.html — one compiled chapter page. The chapter title (plain text,
     auto-escaped) followed by each scene's `contents` rendered Markdown → HTML and
     joined by <hr> (no scene titles). Prev/next reading navigation sits at the top
     AND the bottom; at the ends it points back to the TOC.

     $renderedScenes is an ordered array of already-rendered HTML strings, produced
     by the shared Scene::renderedContents accessor (the single render path also used
     by story/index and shared/scenes/show), so the render choice lives in one place. --}}
@extends('exports.book.layout', ['title' => $chapterTitle])

@section('content')
    <nav class="reading-nav">
        <a href="{{ $prevHref }}" rel="prev">&larr; {{ __('Previous') }}</a>
        <a href="{{ $nextHref }}" rel="next">{{ __('Next') }} &rarr;</a>
    </nav>

    <h1>{{ $chapterTitle }}</h1>

    @foreach ($renderedScenes as $index => $renderedContents)
        @if ($index > 0)
            <hr>
        @endif
        {!! $renderedContents !!}
    @endforeach

    <nav class="reading-nav">
        <a href="{{ $prevHref }}" rel="prev">&larr; {{ __('Previous') }}</a>
        <a href="{{ $nextHref }}" rel="next">{{ __('Next') }} &rarr;</a>
    </nav>
@endsection
