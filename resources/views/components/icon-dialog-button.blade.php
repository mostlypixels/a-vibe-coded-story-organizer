@props(['modal', 'icon' => 'trash', 'variant' => 'danger', 'label' => null])

<x-icon-button
    type="button"
    :icon="$icon"
    :variant="$variant"
    :label="$label ?? __('Delete')"
    x-data=""
    x-on:click="$dispatch('open-modal', '{{ $modal }}')"
    {{ $attributes }}
/>
