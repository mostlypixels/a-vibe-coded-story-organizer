{{-- One codex appendix entry page, added by EpubExporter::addAppendixSection() (task 13): the
     entry's name as a heading plus its rich-HTML `description`. Unlike the front/back-matter
     Markdown pages, a codex `description` is sanitized RICH HTML (not Markdown), so the service
     runs it through RichText::toXhtmlFragment() BEFORE it reaches this view — that helper repairs
     void/unclosed tags into well-formed XHTML so the shipped .xhtml clears EpubExporter's
     validatePackage() gate. The result is trusted, well-formed markup and safe to embed with
     {!! !!}, exactly like a rendered scene body. Rendered only when the description is non-empty,
     so a description-less entry never emits an empty element.

     $imagePath (task 13 — step 2) is the entry's FIRST media image, already packaged by
     EpubExporter::addAppendixEntryImage() (gated by `appendix_include_images`, embedded via
     CoverImageService + the library file API, missing file skipped in the service). It is null
     when images are off, the entry has no image, or the backing file was missing — in which case
     the page renders text only. Shown above the description, styled by section.codex-entry img in
     styles.css (a bounded, centred illustration alongside the text — not a full-bleed cover page
     like chapter-cover.blade.php). --}}
@extends('exports.epub.layout', ['title' => $name])

@section('content')
    <section class="codex-entry">
        <h1>{{ $name }}</h1>
        @if ($imagePath !== null)
            <div class="codex-image"><img src="{{ $imagePath }}" alt="{{ $name }}"/></div>
        @endif
        @if ($description !== '')
            <div class="codex-description">{!! $description !!}</div>
        @endif
    </section>
@endsection
