@props(['active' => false])

@php
$focus = 'focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus';

$classes = $active
    ? "block w-full px-4 py-2 text-start text-sm leading-5 font-semibold text-accent-content bg-accent-surface no-underline hover:no-underline {$focus} transition duration-150 ease-in-out"
    : "block w-full px-4 py-2 text-start text-sm leading-5 text-content-muted no-underline hover:no-underline hover:bg-neutral {$focus} transition duration-150 ease-in-out";
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
