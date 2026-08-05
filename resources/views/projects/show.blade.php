<x-app-layout>
    {{-- Dashboard heading row. The word count and Edit Project link used to sit
         in the header band; the band now carries the breadcrumb trail and its
         right column is reserved (empty) for a future page-actions spec, so these
         move beside the page heading here. `muted` (not `band`) — they sit on the
         page surface now, not the dark band. --}}
    <div class="mb-6 flex items-center justify-between gap-4">
        <x-heading level="1">{{ $project->name }}</x-heading>
        <div class="flex items-center gap-4">
            <x-word-count :count="$wordCount" variant="muted" />
            <a href="{{ route('projects.edit', $project) }}" class="text-sm text-content-muted hover:text-content">
                {{ __('Edit Project') }}
            </a>
        </div>
    </div>

    <div class="space-y-6">
            @if ($project->description)
                <x-card class="text-content">
                    <x-rich-text :html="$project->description" />
                </x-card>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <a href="{{ route('projects.plotlines.index', $project) }}" class="bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg p-6 hover:bg-surface-sunken">
                    <x-heading level="3">{{ __('Plotlines') }}</x-heading>
                    <p class="text-sm text-content-muted mt-1">{{ trans_choice('{0} No plotlines|{1} :count plotline|[2,*] :count plotlines', $project->plotlines_count, ['count' => $project->plotlines_count]) }}</p>
                </a>

                <a href="{{ route('projects.events.index', $project) }}" class="bg-surface-raised overflow-hidden shadow-xs sm:rounded-lg p-6 hover:bg-surface-sunken">
                    <x-heading level="3">{{ __('Events') }}</x-heading>
                    <p class="text-sm text-content-muted mt-1">{{ trans_choice('{0} No events|{1} :count event|[2,*] :count events', $project->events_count, ['count' => $project->events_count]) }}</p>
                </a>
            </div>
    </div>
</x-app-layout>
