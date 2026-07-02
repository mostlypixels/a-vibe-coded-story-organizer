@props(['level' => 2])

@php
    // Render <h1>…<h6> with a single, shared typographic scale so headings look
    // consistent everywhere in the UI. Pick the level for document semantics
    // (outline/accessibility); the visual size follows from the scale below.
    $tag = 'h' . $level;

    $styles = [
        1 => 'text-3xl font-bold text-gray-900 leading-tight',
        2 => 'text-2xl font-bold text-gray-900 leading-tight',
        3 => 'text-xl font-semibold text-gray-800',
        4 => 'text-lg font-semibold text-gray-800',
        5 => 'text-base font-semibold text-gray-700',
        6 => 'text-sm font-semibold uppercase tracking-wider text-gray-500',
    ][$level];
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => $styles]) }}>
    {{ $slot }}
</{{ $tag }}>
