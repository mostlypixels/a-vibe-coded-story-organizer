@extends('exports.epub.layout', ['title' => $title])

@section('content')
    <section class="chapter-cover">
        <img src="{{ $imagePath }}" alt="{{ $title }}"/>
    </section>
@endsection
