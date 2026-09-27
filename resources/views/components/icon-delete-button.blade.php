@props(['action', 'confirm', 'label' => null])

{{-- Opens the layout's shared `confirm-delete` dialog, so a list renders no dialog per row. --}}
<x-icon-button
    type="button"
    icon="trash"
    variant="danger"
    :label="$label ?? __('Delete')"
    x-data=""
    x-on:click.prevent="$dispatch('open-confirm-delete', {{ Js::from(['dialog' => 'confirm-delete', 'action' => $action, 'message' => $confirm, 'confirmLabel' => $label]) }})"
    {{ $attributes }}
/>
