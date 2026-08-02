@props(['active' => false])

@php
// The active item is tinted with `accent-surface`, the same family the active-nav
// indicator uses, so "this is the one you are on" is one colour idea across the
// whole app.
//
// Both states take a real focus ring rather than a background tint. The tint the
// active item used to get on focus was a second shade of its own active tint, and
// the flat vocabulary has no such shade — a ring on the `focus` token is both
// expressible and a stronger affordance.
$focus = 'focus:outline-hidden focus:ring-2 focus:ring-inset focus:ring-focus';

$classes = $active
    ? "block w-full px-4 py-2 text-start text-sm leading-5 font-semibold text-accent-content bg-accent-surface no-underline hover:no-underline {$focus} transition duration-150 ease-in-out"
    : "block w-full px-4 py-2 text-start text-sm leading-5 text-content-muted no-underline hover:no-underline hover:bg-neutral {$focus} transition duration-150 ease-in-out";
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($active) aria-current="page" @endif>{{ $slot }}</a>
