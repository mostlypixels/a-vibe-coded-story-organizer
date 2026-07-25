@props([
    'html' => null,
    'inline' => false,
    'kind' => null,
])

{{--
    The one place diff output is styled.

    Every diff in the app renders through here — the visual diff of a rich field
    and the side-by-side source diff of a Markdown one alike — so the two read as
    one feature instead of drifting apart, and so none of this styling can leak
    into x-rich-text, which renders the author's own content and must never look
    like a diff.

    ## Why {!! !!} is right here, and only here

    $html is always output of App\Services\Diff\DiffHtmlRenderer (or a stored
    summary_html the same renderer produced, or jfcherng's escaped table). That
    class builds every tag from its own allow-list and escapes every text node,
    so the string is safe by construction.

    This component must NEVER sanitize what it is given: the author allow-list
    (RichTextFields::ALLOWED_TAGS) deliberately contains no <ins>/<del>, so
    purifying here would strip exactly the markers the diff exists to show.
    Equally, never pass author content to this component — that is x-rich-text's
    job.

    ## Three channels for every change, never two

    A marked passage carries a background tint, a +/− gutter glyph, AND a
    visually-hidden "inserted"/"removed" label (the renderer emits that last one
    inside each marker). Colour alone is not information, and <ins>/<del>
    announcement is inconsistent across screen readers, so each channel covers
    for the others.

    Strikethrough and underline are never used as markers: the writer can apply
    both herself (<s> and <u> are in the author allow-list), so a struck-out
    passage has to keep meaning "she struck this out".

    The rules live in resources/css/app.css under `.revision-diff` — pseudo-element
    gutter glyphs and descendant selectors over markup this component does not
    emit are far more legible there than as arbitrary Tailwind variants. This is
    still the only view that applies those classes.
--}}

@php
    // The source diff is jfcherng's two-column <table>; the visual diff is a run
    // of rebuilt blocks. Both are diffs and share the marker styling, but only
    // one of them is a table that needs column rules.
    $isSource = $kind !== null && $kind !== \App\Enums\FieldKind::Rich;

    $variant = $inline
        ? 'revision-diff--inline'
        : ($isSource ? 'revision-diff--source' : 'revision-diff--visual');
@endphp

@if (filled($html))
    <div {{ $attributes->merge(['class' => "revision-diff {$variant}"]) }}>
        {!! $html !!}
    </div>
@endif
