@props(['type' => 'submit'])

<x-icon-button
    icon="device-floppy"
    :label="__('Save')"
    class="focus:outline-hidden focus:ring-2 focus:ring-focus focus:ring-offset-2 transition ease-in-out duration-150"
    {{ $attributes->merge(['type' => $type]) }}
/>
