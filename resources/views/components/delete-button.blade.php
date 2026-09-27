@props(['action', 'confirm', 'buttonClass' => null])

{{-- Opens the layout's shared `confirm-delete` dialog. --}}
<div {{ $attributes }}>
    <x-button
        type="button"
        variant="danger"
        :icon="true"
        :class="$buttonClass"
        x-data=""
        x-on:click.prevent="$dispatch('open-confirm-delete', {{ Js::from(['dialog' => 'confirm-delete', 'action' => $action, 'message' => $confirm]) }})"
    >{{ $slot }}</x-button>
</div>
