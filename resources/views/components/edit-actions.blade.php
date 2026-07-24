@props([
    'form',
    'deleteAction' => null,
    'deleteConfirm' => null,
])

{{--
    Shared sidebar block for entity edit pages: Save / Save and stay / Delete, all acting
    on the main edit form via the `form` attribute (the buttons live in the sidebar column,
    outside the <form> tag itself — see resources/views/components/edit-layout.blade.php).

    "Save" submits normally (controller redirects back to the list, as before). "Save and
    stay" submits the same form with a `stay=1` field so the controller redirects back to
    this edit page instead. $slot is the Delete button's label; omit `deleteAction` (e.g.
    a fixed/un-deletable event) to hide the Delete button entirely.

    Pass a `delete` slot to replace the default confirm() delete button with a custom
    trigger (e.g. Act/Chapter's "move or delete" dialog trigger) while keeping the same
    Actions card layout and divider.
--}}
<x-card :title="__('Actions')">
    @if (session('status') === 'saved')
        <x-auth-session-status :status="__('Saved.')" class="mb-3" />
    @endif

    <div class="flex flex-col gap-3">
        <x-button variant="primary" type="submit" form="{{ $form }}" :icon="true" class="w-full">{{ __('Save') }}</x-button>
        <x-button variant="secondary" type="submit" form="{{ $form }}" name="stay" value="1" icon="tabler-device-floppy" class="w-full">{{ __('Save and stay') }}</x-button>
    </div>

    @if ($deleteAction || isset($delete))
        <hr class="mt-4 border-gray-200">

        @isset($delete)
            <div class="mt-4">{{ $delete }}</div>
        @else
            <x-delete-button :action="$deleteAction" :confirm="$deleteConfirm" button-class="w-full" class="mt-4 block">
                {{ $slot }}
            </x-delete-button>
        @endisset
    @endif
</x-card>
