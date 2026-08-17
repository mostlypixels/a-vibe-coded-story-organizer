{{-- books/index.html — the top-level index. Links every book's own table of
     contents (books/NN/index.html), in position order. $books is a plain data
     structure built by StaticSiteExporter (presentation logic stays out of
     Blade); titles are auto-escaped by {{ }}. Every project holds at least one
     book, so this list is never empty. --}}
@extends('exports.books.layout', ['title' => $projectName])

@section('content')
    <h1>{{ $projectName }}</h1>

    <section class="toc">
        <ul>
            @foreach ($books as $book)
                <li><a href="{{ $book['href'] }}">{{ $book['title'] }}</a></li>
            @endforeach
        </ul>
    </section>
@endsection
