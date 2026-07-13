<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('New Attribute') }}
        </x-heading>
    </x-slot>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('projects.codex-attributes.store', $project) }}" class="space-y-6">
                @csrf

                @include('codex-attributes.partials.fields')

                <div class="flex items-center gap-4">
                    <x-button variant="primary">{{ __('Create Attribute') }}</x-button>
                    <a href="{{ route('projects.codex-attributes.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>
    </x-edit-layout>
</x-app-layout>
