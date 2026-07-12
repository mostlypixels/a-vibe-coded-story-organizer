@props(['type' => 'button', 'variant' => 'default'])

@php
    // 'light' is for placement over a dark/photo backdrop (e.g. the reference-image
    // lightbox), where the default navy-on-white outline would be low contrast.
    $variants = [
        'default' => 'border-navy-500 bg-transparent text-navy-500 hover:bg-navy-50',
        'light'   => 'border-white bg-transparent text-white hover:bg-white/10',
    ][$variant];
@endphp

<button {{ $attributes->merge(['type' => $type, 'class' => "inline-flex items-center justify-center p-1.5 rounded-md border $variants"]) }} title="{{ __('Close') }}">
    <span class="sr-only">{{ __('Close') }}</span>
    <x-tabler-x class="h-4 w-4" />
</button>
