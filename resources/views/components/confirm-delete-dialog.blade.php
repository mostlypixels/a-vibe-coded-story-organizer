{{--
    The app's own dialog, not the browser's confirm box: it follows the theme and fonts.

    The app layout renders one shared copy named `confirm-delete`. Delete buttons fill it
    with an `open-confirm-delete` event, so a list does not render one dialog per row.
    A page can still render its own copy with a fixed action and message.
--}}
@props(['name', 'action' => '', 'message' => '', 'confirmLabel' => null])

<div
    x-data="{ action: @js($action), message: @js($message), confirmLabel: @js($confirmLabel ?? __('Delete')) }"
    x-on:open-confirm-delete.window="if ($event.detail.dialog === @js($name)) {
        action = $event.detail.action;
        message = $event.detail.message;
        confirmLabel = $event.detail.confirmLabel ?? @js(__('Delete'));
        $dispatch('open-modal', @js($name));
    }"
>
    <x-dialog :name="$name" :title="$message" title-expression="message" max-width="md">
        <p class="text-sm text-content-muted">{{ __('You cannot undo this.') }}</p>

        <x-slot name="footer">
            <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">
                {{ __('Cancel') }}
            </x-button>

            <form method="POST" action="{{ $action }}" x-bind:action="action">
                @csrf
                @method('DELETE')
                <x-button variant="danger" type="submit" x-text="confirmLabel">{{ $confirmLabel ?? __('Delete') }}</x-button>
            </form>
        </x-slot>
    </x-dialog>
</div>
