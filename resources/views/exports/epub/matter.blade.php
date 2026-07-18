{{-- A generic front/back-matter page: dedication, acknowledgements, preface, or postface.
     One shared view for all four (they differ only in heading text and body content), driven
     by EpubExporter::addMatterSection(). The body is the Project's Markdown field compiled by
     the service's own private SmartPunct converter — never Scene::renderedContents, never the
     rich-HTML sanitizer/RichText helper (these fields are Markdown like Scene.contents, not
     rich HTML like descriptions/codex entries) — so it is trusted, well-formed HTML and safe
     to embed with {!! !!}, exactly like a rendered scene body. --}}
@extends('exports.epub.layout', ['title' => $heading])

@section('content')
    <section class="matter">
        <h1>{{ $heading }}</h1>
        {!! $body !!}
    </section>
@endsection
