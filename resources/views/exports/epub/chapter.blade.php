{{-- A Chapter page: "Chapter {position}: {name}" followed by its Scenes' rendered
     Markdown, joined by <hr/> (no per-scene titles, no Chapter `description`) — the same
     content shape as the book/ reading layer, per the grilled decision.

     $renderedScenes is an ordered array of already-rendered HTML strings produced by
     EpubExporter's own SmartPunct-configured CommonMark converter (NOT
     Scene::renderedContents, which must stay untouched for the rest of the app). The
     <hr/> is self-closed so the document stays XML-well-formed for task 05's validation.
     The <section class="chapter"> root is what styles.css page-breaks. --}}
@extends('exports.epub.layout', ['title' => 'Chapter '.$position.': '.$name])

@section('content')
    <section class="chapter">
        <h1>Chapter {{ $position }}: {{ $name }}</h1>

        @foreach ($renderedScenes as $index => $renderedScene)
            @if ($index > 0)
                <hr/>
            @endif
            {!! $renderedScene !!}
        @endforeach
    </section>
@endsection
