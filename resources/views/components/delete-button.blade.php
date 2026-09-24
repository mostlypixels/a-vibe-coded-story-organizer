@props(['action', 'confirm', 'buttonClass' => null])

{{-- A random name, because one page can show the same delete twice (desktop and phone). --}}
@php($dialog = 'confirm-delete-'.Str::lower(Str::random(8)))

<div {{ $attributes }}>
    <x-button
        type="button"
        variant="danger"
        :icon="true"
        :class="$buttonClass"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', '{{ $dialog }}')"
    >{{ $slot }}</x-button>

    <x-confirm-delete-dialog :name="$dialog" :action="$action" :message="$confirm" />
</div>
