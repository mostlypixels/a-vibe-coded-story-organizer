{{-- A full-page chapter cover image, added by EpubExporter::addChapterCoverPage() (task 12)
     immediately before its chapter's own content page — gated by
     `include_chapter_covers` and only when the chapter has a cover with real bytes on disk.
     Mirrors the shape of the rampmaster/phpepub library's OWN cover page (a single
     full-bleed <img>, no other content), but this is a plain Blade view like every other
     content document — never string-built PHP — and does not carry `epub:type="cover"`,
     which is reserved for the ONE package-level cover the library's setCoverImage() already
     emits ({@see EpubExporter::applyCover()}). .chapter-cover in styles.css sizes the image
     to the page. --}}
@extends('exports.epub.layout', ['title' => $title])

@section('content')
    <section class="chapter-cover">
        <img src="{{ $imagePath }}" alt="{{ $title }}"/>
    </section>
@endsection
