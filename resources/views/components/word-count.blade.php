@props(['count', 'variant' => 'muted'])

{{--
    The one place a word count gets formatted (task 6, word-count spec). A scene's own
    `word_count` and a controller's `withSum`/`sum()` total both render through here, so a
    list and a header can never disagree about "1,234" vs "1234" or singular/plural.

    Props:
      - count — plain int, never a model. Works equally for `$scene->word_count` and a
        computed SUM, which is the whole reason this takes an int rather than an entity.
      - variant — 'muted' (default, small and grey — a header/aside) or 'inline'
        (inherits the surrounding size/colour, for a table cell).

    Zero renders as "0 words", never blank and never a dash: zero is a real answer, blank
    reads as "unknown" (see expanded/ui.md, "Empty and zero states").
--}}
@php
    $classes = $variant === 'inline' ? '' : 'text-xs text-gray-400';

    // Thousands-separated + pluralised via App\Support\WordCountFormat, the one
    // place this translation key lives — task 7's live counter (resources/js/
    // word-count.js) renders the same three branches client-side and must never
    // drift from this string, so both read it from there rather than each
    // carrying their own copy.
    $text = \App\Support\WordCountFormat::text($count);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>{{ $text }}</span>
