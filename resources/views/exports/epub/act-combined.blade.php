{{-- The EPUB library cannot hide spine chapters from EPUB 3 navigation. At Acts depth, one spine document must contain the complete act. --}}
@extends('exports.epub.layout', ['title' => 'Act '.$number])

@section('content')
    @include('exports.epub.partials.act-body')

    @foreach ($chapters as $chapter)
        @include('exports.epub.partials.chapter-body', $chapter)
    @endforeach
@endsection
