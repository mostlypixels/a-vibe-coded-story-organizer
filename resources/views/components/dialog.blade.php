@props([
    'name',
    'title' => null,
    'maxWidth' => '2xl',
])

{{--
    Higher-level modal with a header (title + close button), body, and optional
    footer, built on top of the low-level x-modal shell. Open/close it by
    dispatching browser events, e.g.:
        <x-button x-on:click.prevent="$dispatch('open-modal', 'confirm-delete')">…</x-button>
        <x-dialog name="confirm-delete" :title="__('Delete act?')">
            {{ __('This cannot be undone.') }}
            <x-slot name="footer">
                <x-button variant="secondary" x-on:click="$dispatch('close')">Cancel</x-button>
                <x-button variant="danger">Delete</x-button>
            </x-slot>
        </x-dialog>
--}}
<x-modal :name="$name" :max-width="$maxWidth" focusable>
    @if ($title)
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <x-heading level="3">{{ $title }}</x-heading>
            <x-icon-close-button x-on:click="$dispatch('close')" />
        </div>
    @endif

    <div class="px-6 py-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
            {{ $footer }}
        </div>
    @endisset
</x-modal>
