{{-- Descriptions and bodies are trusted XHTML prepared by EpubExporter. --}}
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
            {{-- Keep navigation targets for scenes that have no title. --}}
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
