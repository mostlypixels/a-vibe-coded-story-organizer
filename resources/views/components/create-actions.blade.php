@props([
    'form',
    'cancel' => null,
])

{{--
    Sidebar Actions card for the create forms — the counterpart of <x-edit-actions>.
    A create page has one action, so this card holds the primary submit and an
    optional Cancel link back to the list. $slot is the submit button's label.

    The buttons sit in the sidebar column, outside the <form> tag, and act on it
    through the `form` attribute — the same wiring <x-edit-actions> uses.

    `data-guard-save` marks the submit as the save action, so the unsaved-changes
    prompt in resources/js/navigation-guard.js stays silent: the submit *is* the save.
--}}
<x-card :title="__('Actions')">
    <div class="flex flex-col gap-3">
        <x-button variant="primary" type="submit" form="{{ $form }}" data-guard-save :icon="true" class="w-full">{{ $slot }}</x-button>

        @if ($cancel)
            <x-button variant="secondary" :href="$cancel" class="w-full">{{ __('Cancel') }}</x-button>
        @endif
    </div>
</x-card>
