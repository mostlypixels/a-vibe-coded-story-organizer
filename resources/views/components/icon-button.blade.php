@props(['as' => 'button', 'variant' => 'outline-solid', 'icon', 'label'])

{{--
    The shared shape of every icon-only control in the app: a square 1.5-unit
    tappable box holding one 16px Tabler glyph, labelled for assistive tech.

    This is the single home of those class strings. They were re-typed in all eight
    `icon-*.blade.php` components and copied raw into two index views, which is how
    a "delete" button in a table ends up a different red from the one in a dialog.
    Compose this instead of restating them.

    An icon alone is never a label: `label` is required and lands in BOTH `title`
    (the hover/sighted affordance) and an `sr-only` span (the accessible name).
    Keep both — a `title` alone is not reliably announced, and an `sr-only` alone
    leaves sighted users guessing at a bare glyph.

    `icon` is the Tabler component name without its prefix, e.g. `trash` for
    `<x-tabler-trash>`.

    `as` picks the element: `button` (default) or `a` for a link. Everything else —
    `href`, `type`, `x-on:click`, `@disabled`, extra classes — falls through
    `$attributes` to that element, so a caller needs no new prop to add behaviour.
--}}

@php
    $variants = [
        // The default outline: the app's navy on white.
        'outline-solid' => 'border border-navy-500 bg-transparent text-navy-500 hover:bg-navy-50',
        // Destructive actions only (delete, revoke) — red is a warning, not a colour choice.
        'danger' => 'border border-red-600 bg-transparent text-red-600 hover:bg-red-50',
        // For placement over a dark or photographic backdrop (e.g. the reference-image
        // lightbox), where the navy-on-white outline would be low contrast.
        'light' => 'border border-white bg-transparent text-white hover:bg-white/10',
        // Borderless, for dense repeated controls inside a table row (reordering),
        // where eight outlined boxes per row would out-shout the content.
        //
        // The unavailable state is expressed as `disabled:` variants rather than as a
        // separate variant the caller selects: the reader's own `@disabled(...)` is
        // then the single source of truth, so the look can never disagree with whether
        // the button actually works — and the Story overview, which toggles `disabled`
        // from JS after an AJAX reorder, restyles itself for free.
        //
        // It greys rather than disappears on purpose: a control that vanishes at the
        // ends of a list makes the row's buttons jump around as things are moved.
        'ghost' => 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'
            .' disabled:text-gray-200 disabled:cursor-not-allowed'
            .' disabled:hover:bg-transparent disabled:hover:text-gray-200',
    ][$variant];
@endphp

<{{ $as }}
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md '.$variants]) }}
    title="{{ $label }}"
>
    <span class="sr-only">{{ $label }}</span>
    <x-dynamic-component :component="'tabler-'.$icon" class="h-4 w-4" />
</{{ $as }}>
