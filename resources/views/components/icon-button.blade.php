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
        // The default outline: the app's link-blue on the page surface.
        'outline-solid' => 'border border-link bg-transparent text-link hover:bg-info-surface',
        // Destructive actions only (delete, revoke) — red is a warning, not a colour choice.
        'danger' => 'border border-danger bg-transparent text-danger hover:bg-danger-surface',
        // For placement over a dark or photographic backdrop (e.g. the reference-image
        // lightbox), where the outline variant would be low contrast. The nav family is
        // the one token pair the app already keeps legible on a fixed-dark surface.
        'light' => 'border border-nav-content bg-transparent text-nav-content hover:bg-nav-content/10',
        // Borderless, for dense repeated controls inside a table row (reordering),
        // where eight outlined boxes per row would out-shout the content.
        //
        // The unavailable state is expressed as `disabled:` variants rather than as a
        // separate variant the caller selects: the reader's own `@disabled(...)` is
        // then the single source of truth, so the look can never disagree with whether
        // the button actually works — and the Story overview, which toggles `disabled`
        // from JS after an AJAX reorder, restyles itself for free.
        //
        // It dims rather than disappears on purpose: a control that vanishes at the
        // ends of a list makes the row's buttons jump around as things are moved.
        //
        // The dimming is `opacity`, not a paler colour: the enabled state is already
        // `content-subtle`, the faintest content token there is, and the flat
        // vocabulary has no second step below it. `disabled:opacity-25` is what
        // `x-button` already uses, so the app has one disabled look rather than two.
        'ghost' => 'text-content-subtle hover:text-content-muted hover:bg-neutral'
            .' disabled:opacity-25 disabled:cursor-not-allowed'
            .' disabled:hover:bg-transparent disabled:hover:text-content-subtle',
    ][$variant];
@endphp

<{{ $as }}
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center p-1.5 rounded-md '.$variants]) }}
    title="{{ $label }}"
>
    <span class="sr-only">{{ $label }}</span>
    <x-dynamic-component :component="'tabler-'.$icon" class="h-4 w-4" />
</{{ $as }}>
