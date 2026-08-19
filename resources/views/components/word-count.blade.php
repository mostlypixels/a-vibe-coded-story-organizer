@props(['count', 'variant' => 'muted'])

@php
    $classes = match ($variant) {
        'inline' => '',
        'band' => 'text-xs text-nav-content-muted',
        default => 'text-xs text-content-subtle',
    };

    $text = \App\Support\WordCountFormat::text($count);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $text }}</span>
