@props(['action', 'confirm', 'label' => null])

{{-- A random name, because one page can show the same delete twice (desktop and phone). --}}
@php($dialog = 'confirm-delete-'.Str::lower(Str::random(8)))

<div class="flex">
    <x-icon-button
        type="button"
        icon="trash"
        variant="danger"
        :label="$label ?? __('Delete')"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', '{{ $dialog }}')"
        {{ $attributes }}
    />

    <x-confirm-delete-dialog :name="$dialog" :action="$action" :message="$confirm" :confirm-label="$label" />
</div>
