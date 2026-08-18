{{-- The description is trusted XHTML prepared by EpubExporter. --}}
<section class="act">
    <h1>Act {{ $number }}</h1>
    @if (filled($name))
        <p class="act-name">{{ $name }}</p>
    @endif
    @if ($showDescription && $description !== '')
        <div class="act-description">{!! $description !!}</div>
    @endif
</section>
