{{-- The combined Act page used at "Acts" table-of-contents depth: the act divider followed
     by every one of its chapters, rendered into a single spine document.

     Why one page per act at this depth? The phpepub library couples spine placement and nav
     entries in addChapter() — there is no way to put a chapter page in the reading order
     without also giving it its own nav entry (setNavHidden() is honoured by the NCX but not
     the EPUB 3 nav; confirmed by spike). So to satisfy "the TOC/nav lists only acts" while
     keeping every chapter's prose readable, each act becomes a single page holding all its
     chapters. Each chapter keeps its own <section class="chapter"> root, so styles.css still
     page-breaks between chapters within the combined page.

     Scene anchors are not emitted here: "Acts" depth never links to scenes. --}}
@extends('exports.epub.layout', ['title' => 'Act '.$position])

@section('content')
    @include('exports.epub.partials.act-body')

    @foreach ($chapters as $chapter)
        @include('exports.epub.partials.chapter-body', $chapter)
    @endforeach
@endsection
