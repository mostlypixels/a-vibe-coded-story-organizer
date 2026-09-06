@props(['scene'])

<article {{ $attributes->merge(['class' => 'prose prose-sm max-w-none text-content-muted text-justify [&_p]:my-4']) }}>
    {!! $scene->renderedContents !!}
</article>
