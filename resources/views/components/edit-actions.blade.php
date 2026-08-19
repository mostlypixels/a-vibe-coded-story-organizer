@props([
    'form',
    'deleteAction' => null,
    'deleteConfirm' => null,
    'historyModel' => null,
    'duplicateModal' => null,
])

<x-card :title="__('Actions')">
    @if (session('status') === 'saved')
        <x-auth-session-status :status="__('Saved.')" class="mb-3" />
    @elseif (session('status') === 'duplicated')
        <x-auth-session-status :status="__('Duplicated.')" class="mb-3" />
    @endif

    <div class="flex flex-col gap-3">
        <x-button variant="primary" type="submit" form="{{ $form }}" data-guard-save :icon="true" class="w-full">{{ __('Save') }}</x-button>
        <x-button variant="secondary" type="submit" form="{{ $form }}" data-guard-save name="stay" value="1" icon="tabler-device-floppy" class="w-full">{{ __('Save and stay') }}</x-button>

        @if ($historyModel)
            <x-entity-history-link :model="$historyModel" class="w-full" />
        @endif

        @if ($duplicateModal)
            <x-button
                variant="secondary"
                type="button"
                icon="tabler-copy"
                class="w-full"
                x-data=""
                x-on:click="$dispatch('open-modal', '{{ $duplicateModal }}')"
            >
                {{ __('Duplicate') }}
            </x-button>
        @endif
    </div>

    @if ($deleteAction || isset($delete))
        <hr class="mt-4 border-border">

        @isset($delete)
            <div class="mt-4">{{ $delete }}</div>
        @else
            <x-delete-button :action="$deleteAction" :confirm="$deleteConfirm" button-class="w-full" class="mt-4 block">
                {{ $slot }}
            </x-delete-button>
        @endisset
    @endif
</x-card>
