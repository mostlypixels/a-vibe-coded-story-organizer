@props(['name', 'action', 'suggestion', 'title'])

{{--
    Shared "name the copy" dialog for the duplicate action (Scene, CodexEntry). Built
    on <x-dialog> for the same reason as <x-delete-with-move-dialog>: a native
    prompt() cannot be labelled or styled.

    The input is prefilled with `$suggestion` but not locked to it — a submitted
    name is accepted even if it collides with an existing one. Open it by
    dispatching `open-modal` with this dialog's `name`.
--}}
<x-dialog :name="$name" :title="$title">
    {{--
        <x-modal focusable> already moves focus into the dialog, but to its first
        focusable element (the header's close button) — this listener re-targets
        that focus onto the name input and selects it, so typing replaces the
        suggestion immediately. The delay clears x-modal's own 100ms focus timer.
    --}}
    {{--
        The footer slot is rendered by <x-dialog> OUTSIDE this <form> element, so a
        plain submit button in it belongs to no form and does nothing when clicked.
        The `form` attribute below re-associates it by id — the same fix
        <x-edit-actions> uses for its sidebar Save buttons.
    --}}
    <form id="{{ $name }}-form" method="POST" action="{{ $action }}" class="space-y-4" x-data x-on:open-modal.window="$event.detail === '{{ $name }}' && setTimeout(() => $refs.name.select(), 150)">
        @csrf

        <div>
            <x-input-label for="{{ $name }}-name" :value="__('Name')" />
            <x-text-input id="{{ $name }}-name" name="name" type="text" class="mt-1 block w-full" x-ref="name" :value="$suggestion" required autofocus />
        </div>

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
            <x-button variant="primary" type="submit" form="{{ $name }}-form">{{ __('Duplicate') }}</x-button>
        </x-slot>
    </form>
</x-dialog>
