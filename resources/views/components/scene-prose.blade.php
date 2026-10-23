{{-- `linkless` keeps only the text of each `entity:` link, for a reader who cannot open the targets. --}}
@props(['scene', 'linkless' => false])

<article {{ $attributes->merge(['class' => 'prose prose-sm manuscript max-w-none text-content-muted text-justify [&_p]:my-4']) }}>
    {!! $linkless ? $scene->linklessContents : $scene->renderedContents !!}
</article>
