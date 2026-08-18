@extends('exports.epub.layout', ['title' => $name])

@section('content')
    <section class="title-page">
        <h1 class="story-title">{{ $name }}</h1>
    </section>
@endsection
