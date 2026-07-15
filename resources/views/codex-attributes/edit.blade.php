<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Edit Attribute') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form id="codex-attribute-edit-form" method="POST" action="{{ route('codex-attributes.update', $attribute) }}" class="space-y-6">
                @csrf
                @method('PUT')

                @include('codex-attributes.partials.fields')

                <div class="flex items-center gap-4">
                    <a href="{{ route('projects.codex-attributes.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-edit-actions
                form="codex-attribute-edit-form"
                :delete-action="route('codex-attributes.destroy', $attribute)"
                :delete-confirm="__('Delete this attribute? Every timeline value recorded for it will be permanently removed.')"
            >
                {{ __('Delete Attribute') }}
            </x-edit-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
