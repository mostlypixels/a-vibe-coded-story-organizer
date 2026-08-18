@extends('exports.books.layout', ['title' => $chapterTitle])

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
