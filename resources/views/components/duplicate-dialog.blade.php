@props(['name', 'action', 'suggestion', 'title'])

<x-dialog :name="$name" :title="$title">
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
