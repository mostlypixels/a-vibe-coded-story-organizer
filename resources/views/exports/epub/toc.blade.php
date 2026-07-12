{{-- The in-book table of contents: every surviving Act with its surviving Chapters nested
     underneath, as a semantic nav/ol/li list of links to the pages EpubExporter packages
     alongside this one. Placed right after the title page in the spine (EpubExporter::
     addFrontMatter()) — distinct from the EPUB 3 nav document the reading app's own TOC
     chrome uses, which the packaging library builds separately. --}}
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
                                    <li><a href="{{ $chapter['href'] }}">{{ $chapter['label'] }}</a></li>
                                @endforeach
                            </ol>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </section>
@endsection
