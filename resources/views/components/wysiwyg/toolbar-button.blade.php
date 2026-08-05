@props([
    // Either a cmd() command name (+ optional args)…
    'command' => null,
    'args' => null,
    // …or a raw JS expression for the bespoke helpers (setLink(), setImage(),
    // setCalloutType()) that don't fit the cmd() shape.
    'action' => null,
    // Highlight when this mark/node is active: [name, ?args] passed to isOn().
    'active' => null,
    // …or a raw JS boolean expression, for the Headings trigger (active on any level).
    'activeExpression' => null,
    // Button glyph, rendered as raw HTML (the labels are entities). Falls back
    // to the slot, which is what the dropdown triggers use for their x-text span.
    'label' => null,
    'title',
    // Set on a dropdown's trigger button only: appends a small chevron, the
    // same "opens a menu" affordance as the rest of the app's dropdowns (see
    // navigation/dropdown-trigger.blade.php, revision-picker.blade.php).
    'dropdown' => false,
])

@php
    // One button shape for every toolbar button, so the base classes live in
    // exactly one place. Both the click handler and the active test come in two
    // flavours (structured or raw JS) and collapse to one expression here.
    // Neither is required: a dropdown trigger has no handler of its own — the
    // wrapping x-dropdown's div owns the click that opens the panel.
    $clickExpression = $action ?? ($command
        ? 'cmd('.Illuminate\Support\Js::from($command)
            .($args ? ', '.Illuminate\Support\Js::from($args) : '').')'
        : null);

    $isActiveExpression = $activeExpression ?? ($active
        ? 'isOn('.Illuminate\Support\Js::from($active[0])
            .(isset($active[1]) ? ', '.Illuminate\Support\Js::from($active[1]) : '').')'
        : null);

    // A button that can never be active gets its resting colours statically.
    // These must be merged into $attributes rather than written as a second
    // class="" — duplicate attributes are not merged by the browser, they are
    // dropped, which silently cost these buttons their sizing and padding.
    $classes = 'inline-flex min-w-8 items-center justify-center whitespace-nowrap rounded-sm px-2 py-1 text-sm font-medium'
        .($isActiveExpression ? '' : ' text-content-muted hover:bg-neutral');

    // Assemble every attribute here so the button tag below carries no inline
    // @if directives. Conditional attributes written between the tag's real
    // attributes stop HTML tooling from finding the opening tag's `>`, so it
    // reports the element as never closed — a false positive, but avoidable.
    $buttonAttributes = $attributes->merge(['class' => $classes]);

    if ($clickExpression) {
        $buttonAttributes = $buttonAttributes->merge(['@click' => $clickExpression]);
    }

    if ($isActiveExpression) {
        // Only the active test is dynamic; the two class strings are literal.
        // Pre-encode just the expression (as HtmlString) so merge()'s escaping
        // leaves the literal quotes intact — byte-for-byte the old output.
        $buttonAttributes = $buttonAttributes->merge([
            ':class' => new \Illuminate\Support\HtmlString(
                '('.e($isActiveExpression).") ? 'bg-accent-surface text-accent-content' : 'text-content-muted hover:bg-neutral'"
            ),
        ]);
    }
@endphp

<button type="button" {{ $buttonAttributes }} title="{{ $title }}" aria-label="{{ $title }}">@if ($dropdown)
    <span class="inline-flex items-center gap-0.5">
        {!! $label ?? $slot !!}
        <x-tabler-chevron-down class="h-3 w-3 shrink-0" />
    </span>
@else
    {!! $label ?? $slot !!}
@endif</button>
