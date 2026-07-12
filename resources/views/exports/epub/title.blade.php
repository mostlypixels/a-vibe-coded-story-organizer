{{-- The story title page: the first content document in the book, before the table of
     contents. Just the Project's name, centered and in larger text via .story-title in
     styles.css — an explicit exception to the "no fonts/spacing" rule, scoped to this
     heading and the Act headings only (see styles.css). --}}
@extends('exports.epub.layout', ['title' => $name])

@section('content')
    <section class="title-page">
        <h1 class="story-title">{{ $name }}</h1>
    </section>
@endsection
