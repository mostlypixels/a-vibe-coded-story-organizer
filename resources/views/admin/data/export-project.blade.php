<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    @include('admin.data.partials.subnav')

    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Export project') }}</x-heading>
        </x-slot>

        <p class="text-sm text-gray-600">
            {{ __('Download one of your projects as a .zip archive: a human-readable reading version plus a lossless data copy.') }}
        </p>

        @if ($projects->isEmpty())
            {{-- Empty state: nothing to export until the user owns a project. --}}
            <p class="mt-4 text-sm text-gray-600">
                {{ __('Create a project first to export it.') }}
                <a href="{{ route('projects.create') }}" class="text-ocean-600 underline hover:text-ocean-800">
                    {{ __('Create a project') }}
                </a>
            </p>
        @else
            <form method="POST" action="{{ route('admin.data.export') }}" class="mt-6 space-y-6 max-w-lg">
                @csrf

                <div>
                    <x-input-label for="project_id" :value="__('Project')" />
                    <select
                        id="project_id"
                        name="project_id"
                        class="mt-1 block w-full border-gray-300 focus:border-ocean-500 focus:ring-ocean-500 rounded-md shadow-sm"
                    >
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
                </div>

                <div>
                    <label for="include_images" class="inline-flex items-center">
                        <input
                            id="include_images"
                            type="checkbox"
                            name="include_images"
                            value="1"
                            @checked(old('include_images', true))
                            class="rounded border-gray-300 text-ocean-600 shadow-sm focus:ring-ocean-500"
                        >
                        <span class="ms-2 text-sm text-gray-700">{{ __('Include images & files') }}</span>
                    </label>
                    <x-input-error :messages="$errors->get('include_images')" class="mt-2" />
                </div>

                <x-button variant="primary">{{ __('Export') }}</x-button>
            </form>
        @endif
    </x-card>
</x-admin-layout>
