@props(['count', 'variant' => 'muted'])

{{--
    The one place a word count gets formatted. A scene's own
    `word_count` and a controller's `withSum`/`sum()` total both render through here, so a
    list and a header can never disagree about "1,234" vs "1234" or singular/plural.

    Props:
      - count — plain int, never a model. Works equally for `$scene->word_count` and a
        computed SUM, which is the whole reason this takes an int rather than an entity.
      - variant — 'muted' (default, small and grey — an aside on a page surface),
        'inline' (inherits the surrounding size/colour, for a table cell), or 'band'
        (the same aside sitting on the dark page-header band).

    'band' exists because `content-subtle` is authored against `surface` and measures
    2.25:1 on `nav-raised` — the nav family has its own foregrounds for exactly this
    reason. Reach for it whenever this component renders inside a layout's `header`
    slot; `nav-content-muted` is the band's muted voice.

    Zero renders as "0 words", never blank and never a dash: zero is a real answer, blank
    reads as "unknown" (see expanded/ui.md, "Empty and zero states").
--}}
@php
    $classes = match ($variant) {
        'inline' => '',
        'band' => 'text-xs text-nav-content-muted',
        default => 'text-xs text-content-subtle',
    };

    // Thousands-separated + pluralised via App\Support\WordCountFormat, the one
    // place this translation key lives — the live counter (resources/js/
    // word-count.js) renders the same three branches client-side and must never
    // drift from this string, so both read it from there rather than each
    // carrying their own copy.
    $text = \App\Support\WordCountFormat::text($count);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $text }}</span>
