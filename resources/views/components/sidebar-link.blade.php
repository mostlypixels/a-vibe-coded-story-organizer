@props([
    'href',
    'active' => false,
    'variant' => 'sidebar',
])

@php
    $stateClasses = match ([$variant, $active]) {
        ['sidebar', true] => 'border-accent bg-accent-surface text-accent-content font-semibold',
        ['sidebar', false] => 'border-transparent text-content-muted hover:bg-neutral hover:text-content',
        ['tab', true] => 'border-accent text-content',
        ['tab', false] => 'border-transparent text-content-muted hover:text-content hover:border-border-strong',
    };
@endphp

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'no-underline hover:no-underline focus:outline-hidden transition duration-150 ease-in-out',
        $stateClasses,
    ]) }}
>{{ $slot }}</a>
