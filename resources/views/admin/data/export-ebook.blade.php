<x-admin-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ __('Configuration') }}
        </x-heading>
    </x-slot>

    @include('admin.data.partials.subnav')

    <x-card>
        <x-slot name="header">
            <x-heading level="3">{{ __('Export ebook') }}</x-heading>
        </x-slot>

        <p class="text-sm text-gray-600">
            {{ __('Download one of your projects as an EPUB e-book, ready to open in any e-reader.') }}
        </p>

        {{--
            The EPUB configuration form (project settings, toggles, section
            ordering) lands in a later task. For now this page hosts only the
            existing download form, moved here verbatim from the old tabbed
            index (task 03: purely a structural split, no behaviour change).
        --}}
        @if ($projects->isEmpty())
            <p class="mt-4 text-sm text-gray-600">
                {{ __('Create a project first to export it.') }}
                <a href="{{ route('projects.create') }}" class="text-ocean-600 underline hover:text-ocean-800">
                    {{ __('Create a project') }}
                </a>
            </p>
        @else
            <form method="POST" action="{{ route('admin.data.export.epub') }}" class="mt-6 space-y-6 max-w-lg">
                @csrf

                <div>
                    <x-input-label for="epub_project_id" :value="__('Project')" />
                    <select
                        id="epub_project_id"
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

                <x-button variant="primary">{{ __('Download EPUB') }}</x-button>
            </form>

            <p class="mt-4 text-xs text-gray-500">
                {{ __('For full EPUB conformance verification, validate the downloaded file with the official') }}
                <a href="https://www.w3.org/publishing/epubcheck/" class="text-ocean-600 underline hover:text-ocean-800 focus:outline-none focus:ring-2 focus:ring-ocean-500 focus:ring-offset-2 rounded-sm" target="_blank" rel="noopener">
                    {{ __('epubcheck') }}
                </a>
                {{ __('tool.') }}
            </p>
        @endif
    </x-card>
</x-admin-layout>
