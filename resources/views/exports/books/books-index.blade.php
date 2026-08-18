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
