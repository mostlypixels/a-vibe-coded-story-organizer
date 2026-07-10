{{-- book/index.html — the table of contents. Lists each act (title) with its
     chapters (titles) as links to the compiled chapter pages, in position order.
     $toc is a plain data structure built by StaticSiteExporter (presentation logic
     stays out of Blade); titles are auto-escaped by {{ }}. --}}
@extends('exports.book.layout', ['title' => $projectName])

@section('content')
    <h1>{{ $projectName }}</h1>

    @forelse ($toc as $act)
        <section class="toc">
            <h2>{{ $act['title'] }}</h2>

            @if (! empty($act['chapters']))
                <ul>
                    @foreach ($act['chapters'] as $chapter)
                        <li><a href="{{ $chapter['href'] }}">{{ $chapter['title'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @empty
        <p>{{ __('This book has no chapters yet.') }}</p>
    @endforelse
@endsection
