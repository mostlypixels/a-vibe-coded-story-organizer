@props(['dialog', 'action', 'suggestion'])

{{-- Fills and opens the list's shared `x-duplicate-dialog` named $dialog. --}}
<x-icon-button
    type="button"
    icon="copy"
    variant="outline-solid"
    :label="__('Duplicate')"
    x-data=""
    x-on:click="$dispatch('open-duplicate', {{ Js::from(['dialog' => $dialog, 'action' => $action, 'suggestion' => $suggestion]) }})"
    {{ $attributes }}
/>
