<x-app-layout>
    <x-page-heading>
        {{ __('New Attribute') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form method="POST" action="{{ route('projects.codex-attributes.store', $project) }}" class="space-y-6">
                @csrf

                @include('codex-attributes.partials.fields')

                <div class="flex items-center gap-4">
                    <x-button variant="primary">{{ __('Create Attribute') }}</x-button>
                    <a href="{{ route('projects.codex-attributes.index', $project) }}" class="text-sm text-content-muted hover:text-content">{{ __('Cancel') }}</a>
                </div>
            </form>
        </x-card>
    </x-edit-layout>
</x-app-layout>
