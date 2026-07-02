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
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-ocean-500" title="{{ __('Close') }}">
                <span class="sr-only">{{ __('Close') }}</span>
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
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
