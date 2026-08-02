@props(['level' => 2])

@php
    // Render <h1>…<h6> with a single, shared typographic scale so headings look
    // consistent everywhere in the UI. Pick the level for document semantics
    // (outline/accessibility); the visual size follows from the scale below.
    //
    // Two content tokens, not six shades of grey: levels 1–3 are the page's own
    // voice (`content`), 4–6 are secondary labels (`content-muted`). Size and
    // weight already separate the levels, and a theme only gets to choose two
    // body-text emphases — inventing a third here would be a value no preset
    // defines.
    $tag = 'h' . $level;

    $styles = [
        1 => 'text-3xl font-bold text-content leading-tight',
        2 => 'text-xl font-semibold text-content leading-tight',
        3 => 'text-lg font-semibold text-content',
        4 => 'text-base font-semibold text-content-muted',
        5 => 'text-sm font-semibold uppercase tracking-wider text-content-muted',
        6 => 'text-xs font-semibold uppercase tracking-wide text-content-muted',
    ][$level];
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => $styles]) }}>
    {{ $slot }}
</{{ $tag }}>
