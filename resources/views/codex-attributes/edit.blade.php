<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Attribute') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('codex-attributes.update', $attribute) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('codex-attributes.partials.fields')

                <div class="flex items-center gap-4">
                    <x-button variant="primary" :icon="true">{{ __('Save') }}</x-button>
                    <a href="{{ route('projects.codex-attributes.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                </div>
            </form>

            <div class="mt-8 border-t border-gray-200 pt-6">
                <x-delete-button :action="route('codex-attributes.destroy', $attribute)" :confirm="__('Delete this attribute? Every timeline value recorded for it will be permanently removed.')">
                    {{ __('Delete Attribute') }}
                </x-delete-button>
            </div>
        </x-card>
    </x-edit-layout>
</x-app-layout>
