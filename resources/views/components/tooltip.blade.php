@props(['text', 'position' => 'top'])

@php
    // Wrap any trigger (icon button, link, …) in the default slot and it shows a
    // small tooltip on hover/focus. Keyboard-accessible via focusin/focusout.
    $positions = [
        'top'    => 'bottom-full left-1/2 -translate-x-1/2 mb-2',
        'bottom' => 'top-full left-1/2 -translate-x-1/2 mt-2',
        'left'   => 'right-full top-1/2 -translate-y-1/2 mr-2',
        'right'  => 'left-full top-1/2 -translate-y-1/2 ml-2',
    ][$position];
@endphp

<span
    x-data="{ show: false }"
    @mouseenter="show = true"
    @mouseleave="show = false"
    @focusin="show = true"
    @focusout="show = false"
    {{ $attributes->merge(['class' => 'relative inline-flex']) }}
>
    {{ $slot }}

    <span
        x-show="show"
        x-transition
        role="tooltip"
        class="absolute z-50 {{ $positions }} whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs font-medium text-white shadow-lg"
        style="display: none;"
    >
        {{ $text }}
    </span>
</span>
