{{-- The inner body of an Act divider: "Act {position}" plus the Act's name on its own
     line, and (config-gated) the Act description. Shared verbatim by the standalone act
     page (act.blade.php) and the combined act page used at "Acts" TOC depth
     (act-combined.blade.php), so the two can never drift.

     The name line is omitted entirely when the Act has no name. The Act `description`
     (rich HTML, pre-normalised to well-formed XHTML by RichText::toXhtmlFragment(), hence
     {!! !!}) is rendered only when `include_act_descriptions` is on AND non-empty. The
     <section class="act"> root is what styles.css page-breaks. --}}
<section class="act">
    <h1>Act {{ $position }}</h1>
    @if (filled($name))
        <p class="act-name">{{ $name }}</p>
    @endif
    @if ($showDescription && $description !== '')
        <div class="act-description">{!! $description !!}</div>
    @endif
</section>
