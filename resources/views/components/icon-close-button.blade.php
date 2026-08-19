@props(['variant' => 'outline-solid'])

<x-icon-button icon="x" :label="__('Close')" :variant="$variant" {{ $attributes->merge(['type' => 'button']) }} />
