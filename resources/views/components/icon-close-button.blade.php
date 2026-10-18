@props(['variant' => 'outline-solid', 'label' => null])

<x-icon-button icon="x" :label="$label ?? __('Close')" :variant="$variant" {{ $attributes->merge(['type' => 'button']) }} />
