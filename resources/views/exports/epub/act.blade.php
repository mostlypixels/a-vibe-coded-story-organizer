{{-- The standalone Act divider page (its own spine document at the default "Chapters" and
     the "Scenes" TOC depth). The body markup lives in partials/act-body.blade.php so the
     combined act page ("Acts" depth) can reuse it verbatim. The <section class="act"> root
     is what styles.css page-breaks. --}}
@extends('exports.epub.layout', ['title' => 'Act '.$position])

@section('content')
    @include('exports.epub.partials.act-body')
@endsection
