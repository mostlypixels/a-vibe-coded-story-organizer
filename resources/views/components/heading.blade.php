@props(['level' => 2, 'icon' => null])

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

    if ($icon) {
        $styles .= ' flex items-center gap-2';
    }
@endphp

<{{ $tag }} {{ $attributes->merge(['class' => $styles]) }}>
    @if ($icon)
        <x-dynamic-component :component="$icon" class="h-5 w-5 shrink-0 text-content-muted" aria-hidden="true" />
    @endif
    {{ $slot }}
</{{ $tag }}>
