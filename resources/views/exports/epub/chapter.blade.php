{{-- The standalone Chapter page (its own spine document at the default "Chapters" and the
     "Scenes" TOC depth). The body markup lives in partials/chapter-body.blade.php so the
     combined act page ("Acts" depth) can reuse it verbatim. The layout <title> falls back
     to "Chapter {position}" so the XHTML document title is never empty (only possible with
     the title-only format on a name-less chapter). --}}
@extends('exports.epub.layout', ['title' => $heading !== '' ? $heading : 'Chapter '.$position])

@section('content')
    @include('exports.epub.partials.chapter-body')
@endsection
