{{--
    A page for one entity passes `action` and `suggestion`. A list renders one copy
    without them, and each row's `x-icon-duplicate-button` fills it on open.
--}}
@props(['name', 'title', 'action' => '', 'suggestion' => ''])

<div
    x-data="{ action: @js($action), suggestion: @js($suggestion) }"
    x-on:open-duplicate.window="if ($event.detail.dialog === @js($name)) {
        action = $event.detail.action;
        suggestion = $event.detail.suggestion;
        $dispatch('open-modal', @js($name));
    }"
>
    <x-dialog :name="$name" :title="$title">
        <form id="{{ $name }}-form" method="POST" action="{{ $action }}" x-bind:action="action" class="space-y-4" x-on:open-modal.window="$event.detail === '{{ $name }}' && setTimeout(() => $refs.name.select(), 150)">
            @csrf

            <div>
                <x-input-label for="{{ $name }}-name" :value="__('Name')" />
                <x-text-input id="{{ $name }}-name" name="name" type="text" class="mt-1 block w-full" x-ref="name" :value="$suggestion" x-model="suggestion" required autofocus />
            </div>

            <x-slot name="footer">
                <x-button variant="secondary" type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-button>
                <x-button variant="primary" type="submit" form="{{ $name }}-form">{{ __('Duplicate') }}</x-button>
            </x-slot>
        </form>
    </x-dialog>
</div>
