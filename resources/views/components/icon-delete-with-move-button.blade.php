@props(['dialog', 'action', 'exclude', 'childCount', 'childSingular', 'childPlural'])

{{-- Fills and opens the list's shared `x-delete-with-move-list-dialog` named $dialog. --}}
@php
    $detail = [
        'dialog' => $dialog,
        'action' => $action,
        'exclude' => $exclude,
        'children' => \App\Support\CountPhrase::make($childCount, $childSingular, $childPlural),
    ];
@endphp

<x-icon-button
    type="button"
    icon="trash"
    variant="danger"
    :label="__('Delete')"
    x-data=""
    x-on:click="$dispatch('open-delete-with-move', {{ Js::from($detail) }})"
    {{ $attributes }}
/>
