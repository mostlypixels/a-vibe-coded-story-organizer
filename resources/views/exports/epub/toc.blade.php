{{-- The in-book table of contents. Its depth follows the TableOfContentsDepth setting,
     mirroring the EPUB 3 nav document EpubExporter::addBody() builds:
       - "Acts": one link per Act, no nested lists.
       - "Chapters" (default): every Act with its surviving Chapters nested underneath.
       - "Scenes": a third level of per-scene anchor links (chapter-{id}.xhtml#scene-{id})
         nested under each Chapter.
     The nesting is driven purely by whether each entry carries a non-empty children array
     (EpubExporter::renderToc() populates them per depth) — this view holds no depth logic
     of its own. Placed wherever `section_order` puts the `toc` key (EpubExporter::addSections()),
     distinct from the EPUB 3 nav document the reading app's own TOC chrome uses. --}}
@extends('exports.epub.layout', ['title' => 'Table of Contents'])

@section('content')
    <section class="toc">
        <h1>Table of Contents</h1>
        <nav epub:type="toc">
            <ol>
                @foreach ($entries as $act)
                    <li>
                        <a href="{{ $act['href'] }}">{{ $act['label'] }}</a>
                        @if (! empty($act['chapters']))
                            <ol>
                                @foreach ($act['chapters'] as $chapter)
                                    <li>
                                        <a href="{{ $chapter['href'] }}">{{ $chapter['label'] }}</a>
                                        @if (! empty($chapter['scenes']))
                                            <ol>
                                                @foreach ($chapter['scenes'] as $scene)
                                                    <li><a href="{{ $scene['href'] }}">{{ $scene['label'] }}</a></li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </section>
@endsection
