{{-- The inner body of a Chapter page. Shared verbatim by the standalone chapter page
     (chapter.blade.php) and the combined act page used at "Acts" TOC depth
     (act-combined.blade.php), so the two can never drift.

     The heading text is pre-formatted by ChapterTitleFormat (the single source shared with
     the nav/TOC label) — plain text, escaped with {{ }}. The optional Chapter description,
     per-scene titles, and per-scene descriptions are all config-gated by flags passed in
     the view data (no `match` on raw strings here). The rich-HTML descriptions arrive
     already normalised to well-formed XHTML by RichText::toXhtmlFragment(), and scene
     bodies are pre-rendered by EpubExporter's own SmartPunct converter — both are trusted
     markup, emitted with {!! !!}.

     When $sceneAnchors is on (only at "Scenes" TOC depth), each scene is preceded by an
     empty `id="scene-{id}"` anchor so the third-level nav / in-book TOC links
     (chapter-{id}.xhtml#scene-{id}) resolve to a real target. The anchor is emitted
     independently of scene titles so the link exists even for untitled scenes. At the
     default "Chapters" depth no anchors are added, keeping the page byte-for-byte as before.

     Empty content with its toggle on renders nothing at all: every optional block is
     guarded on a non-empty value so the document never carries a blank heading/element.

     Scenes are joined by the configured divider snippet ($dividerHtml — a self-closed
     <hr/> or a decorative ornament), keeping the document XML-well-formed for the export's
     validation. The <section class="chapter"> root is what styles.css page-breaks. --}}
<section class="chapter">
    @if ($heading !== '')
        <h1>{{ $heading }}</h1>
    @endif

    @if ($showChapterDescription && $chapterDescription !== '')
        <div class="chapter-description">{!! $chapterDescription !!}</div>
    @endif

    @foreach ($scenes as $index => $scene)
        @if ($index > 0)
            {!! $dividerHtml !!}
        @endif
        @if ($sceneAnchors)
            <div class="scene-anchor" id="scene-{{ $scene['id'] }}"></div>
        @endif
        @if ($showSceneTitles && $scene['title'] !== '')
            <h2 class="scene-title">{{ $scene['title'] }}</h2>
        @endif
        @if ($showSceneDescriptions && $scene['description'] !== '')
            <div class="scene-description">{!! $scene['description'] !!}</div>
        @endif
        {!! $scene['body'] !!}
    @endforeach
</section>
