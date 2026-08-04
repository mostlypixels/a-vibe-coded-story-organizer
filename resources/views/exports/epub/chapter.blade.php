{{-- The standalone Chapter page (its own spine document at the default "Chapters" and the
     "Scenes" TOC depth). The body markup lives in partials/chapter-body.blade.php so the
     combined act page ("Acts" depth) can reuse it verbatim. The layout <title> falls back
     to "Chapter {number}" so the XHTML document title is never empty (only possible with
     the title-only format on a name-less chapter). $number is the project-wide, gap-free
     rank from App\Support\StoryNumbering — NOT the Chapter's `position` column. --}}
@extends('exports.epub.layout', ['title' => $heading !== '' ? $heading : 'Chapter '.$number])

@section('content')
    @include('exports.epub.partials.chapter-body')
@endsection
