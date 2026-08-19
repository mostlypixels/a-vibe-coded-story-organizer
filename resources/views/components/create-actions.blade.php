@props([
    'form',
    'cancel' => null,
])

<x-card :title="__('Actions')">
    <div class="flex flex-col gap-3">
        <x-button variant="primary" type="submit" form="{{ $form }}" data-guard-save :icon="true" class="w-full">{{ $slot }}</x-button>

        @if ($cancel)
            <x-button variant="secondary" :href="$cancel" class="w-full">{{ __('Cancel') }}</x-button>
        @endif
    </div>
</x-card>
