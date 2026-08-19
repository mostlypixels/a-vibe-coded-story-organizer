{{-- EpubExporter converts the Markdown body to trusted, well-formed XHTML. --}}
@extends('exports.epub.layout', ['title' => $heading])

@section('content')
    <section class="matter">
        <h1>{{ $heading }}</h1>
        {!! $body !!}
    </section>
@endsection
