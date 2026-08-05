<x-revisions-layout :project="$project">
    <x-page-heading>
        {{ __('Revisions') }} &mdash; {{ $project->name }}
    </x-page-heading>

    {{-- Landing pane: the sidebar is the browser's real navigation, so this pane
         just orients the reader. When the project has no history yet the sidebar
         shows its own empty state, so a neutral prompt here reads correctly either
         way. --}}
    <x-card>
        <div class="max-w-prose space-y-3">
            <x-heading level="3">{{ __('Browse this project\'s revision history') }}</x-heading>
            <p class="text-content-muted">
                {{ __('The sidebar lists every entity and field that has saved revisions. Pick a field to see its full history, revert to an earlier version, or compare two versions side by side.') }}
            </p>
            <p class="text-sm text-content-muted">
                {{ __('Only fields that have been edited appear here — the number beside each field is how many revisions it has.') }}
            </p>
        </div>
    </x-card>
</x-revisions-layout>
