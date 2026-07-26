@props(['type' => 'submit'])

{{-- Carries a focus ring the other icon buttons don't: this one is a form's primary
     action, so its keyboard focus state has to be unmistakable. --}}
<x-icon-button
    icon="device-floppy"
    :label="__('Save')"
    class="focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 transition ease-in-out duration-150"
    {{ $attributes->merge(['type' => $type]) }}
/>
