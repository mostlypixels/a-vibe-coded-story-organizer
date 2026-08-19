@extends('exports.epub.layout', ['title' => $heading !== '' ? $heading : 'Chapter '.$number])

@section('content')
    @include('exports.epub.partials.chapter-body')
@endsection
