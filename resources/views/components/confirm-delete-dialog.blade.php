{{-- The app's own dialog, not the browser's confirm box: it follows the theme and fonts. --}}
@props(['name', 'action', 'message', 'confirmLabel' => null])

<x-dialog :name="$name" :title="$message" max-width="md">
    <p class="text-sm text-content-muted">{{ __('You cannot undo this.') }}</p>

    <x-slot name="footer">
        <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
            {{ __('Cancel') }}
        </x-button>

        <form method="POST" action="{{ $action }}">
            @csrf
            @method('DELETE')
            <x-button variant="danger" type="submit">{{ $confirmLabel ?? __('Delete') }}</x-button>
        </form>
    </x-slot>
</x-dialog>
