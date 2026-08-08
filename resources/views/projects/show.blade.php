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

            {{-- Where the author left off, one tile per entity kind. Ordered
                 the way the top-level menu is: Story, then Timeline, then
                 Codex. --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-latest-tile
                    :label="__('Latest act')"
                    :item="$latest['act']"
                    :fallback-url="route('projects.acts.index', $project)"
                />

                <x-latest-tile
                    :label="__('Latest chapter')"
                    :item="$latest['chapter']"
                    :fallback-url="route('projects.chapters.index', $project)"
                />

                <x-latest-tile
                    :label="__('Latest scene')"
                    :item="$latest['scene']"
                    :fallback-url="route('projects.scenes.index', $project)"
                />

                <x-latest-tile
                    :label="__('Latest plotline')"
                    :item="$latest['plotline']"
                    :fallback-url="route('projects.plotlines.index', $project)"
                />

                <x-latest-tile
                    :label="__('Latest event')"
                    :item="$latest['event']"
                    :fallback-url="route('projects.events.index', $project)"
                />

                @foreach (\App\Enums\CodexEntryType::cases() as $codexType)
                    <x-latest-tile
                        :label="__('Latest :type', ['type' => __($codexType->singularNoun())])"
                        :item="$latestCodexEntries[$codexType->value]"
                        :fallback-url="route('projects.codex.index', [$project, $codexType->routeKey()])"
                    />
                @endforeach
            </div>
    </div>
</x-app-layout>
