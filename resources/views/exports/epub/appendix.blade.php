{{-- The codex appendix section heading/cover page, added by EpubExporter::addAppendixSection()
     (task 13) at the `appendix` slot of the section_order walk — the closing back-matter of the
     book, only when `include_codex_appendix` is on, at least one `appendix_entry_types` is
     selected, and the project actually has matching entries. It is the root nav entry the
     individual entry pages nest underneath, mirroring how an Act heading owns its Chapters. A
     plain content document like every other epub page (never string-built PHP); .appendix in
     styles.css centres it like the act dividers. --}}
@extends('exports.epub.layout', ['title' => $heading])

@section('content')
    <section class="appendix">
        <h1>{{ $heading }}</h1>
    </section>
@endsection
