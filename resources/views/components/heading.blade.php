@props(['level' => 2])

@php
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
