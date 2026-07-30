@props([
    // Either a cmd() command name (+ optional args)…
    'command' => null,
    'args' => null,
    // …or a raw JS expression for the bespoke helpers (setLink(), setImage(),
    // toggleCallout()) that take no arguments and don't fit the cmd() shape.
    'action' => null,
    // Highlight when this mark/node is active: [name, ?args] passed to isOn().
    'active' => null,
    // …or a raw JS boolean expression, for the Headings trigger (active on any level).
    'activeExpression' => null,
    // Button glyph, rendered as raw HTML (the labels are entities). Falls back
    // to the slot, which is what the dropdown triggers use for their x-text span.
    'label' => null,
    'title',
])

@php
    // One <button> shape for every toolbar button, so the base classes live in
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
    $classes = 'inline-flex min-w-[2rem] items-center justify-center rounded px-2 py-1 text-sm font-medium'
        .($isActiveExpression ? '' : ' text-gray-600 hover:bg-gray-200');
@endphp

<button
    type="button"
    @if ($clickExpression)
        @click="{{ $clickExpression }}"
    @endif
    @if ($isActiveExpression)
        :class="({{ $isActiveExpression }}) ? 'bg-ocean-100 text-ocean-800' : 'text-gray-600 hover:bg-gray-200'"
    @endif
    {{ $attributes->merge(['class' => $classes]) }}
    title="{{ $title }}"
    aria-label="{{ $title }}"
>{!! $label ?? $slot !!}</button>
