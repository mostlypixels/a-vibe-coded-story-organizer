@props(['variant' => 'outline-solid'])

{{-- Pass variant="light" over a dark/photo backdrop (e.g. the reference-image
     lightbox), where the navy-on-white outline would be low contrast. --}}
<x-icon-button icon="x" :label="__('Close')" :variant="$variant" {{ $attributes->merge(['type' => 'button']) }} />
