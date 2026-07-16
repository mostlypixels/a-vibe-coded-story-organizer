<x-app-layout>
    <x-slot name="header">
        <x-heading level="2">
            {{ $project->name }} &mdash; {{ __('Search') }}
        </x-heading>
    </x-slot>

    <div class="space-y-6">
        {{-- Search form. Plain GET (no AJAX): q/mode round-trip via the query string
             so results are bookmarkable and survive a refresh. The mode control is a
             <fieldset> radio group (keyboard-accessible, semantic) over SearchMode. --}}
        <form method="GET" action="{{ route('projects.search.index', $project) }}" class="bg-white shadow-sm rounded-lg p-6 space-y-4">
            <div class="space-y-1">
                <x-input-label for="q" :value="__('Search this project')" />
                <div class="flex gap-2">
                    <x-text-input
                        id="q"
                        type="search"
                        name="q"
                        :value="$query"
                        class="flex-1"
                        :placeholder="__('Search…')"
                        autofocus
                    />
                    <x-button type="submit" variant="primary">{{ __('Search') }}</x-button>
                </div>
            </div>

            <fieldset class="space-y-1">
                <legend class="block font-medium text-sm text-gray-700">{{ __('Match') }}</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach (\App\Enums\SearchMode::cases() as $searchMode)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="radio"
                                name="mode"
                                value="{{ $searchMode->value }}"
                                @checked($mode === $searchMode)
                                class="border-gray-300 text-navy-900 focus:ring-ocean-500"
                            />
                            {{ $searchMode->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </form>

        {{-- Results. Rendered only after a search ran ($results !== null). A search
             that matched nothing anywhere shows ONE friendly page-level message, not
             three empty per-section blocks. --}}
        @if ($results !== null)
            @if ($results->isEmpty())
                <div class="bg-white shadow-sm rounded-lg px-6 py-10 text-center text-gray-500">
                    <p class="font-medium text-gray-600">
                        {{ __('No results match “:query”.', ['query' => $query]) }}
                    </p>
                    <p class="mt-1 text-sm">{{ __('Try a different word or switch the match mode.') }}</p>
                </div>
            @else
                {{-- Entity types with no matches are hidden (x-search.result-table
                     renders nothing when its rows are empty), and a section whose
                     tables are ALL empty is skipped entirely — no orphaned heading.
                     SearchResults::has*Matches() owns that per-section check. --}}
                <div class="space-y-8">
                    {{-- Timeline — Plotlines, Events. --}}
                    @if ($results->hasTimelineMatches())
                        <x-search.section :title="__('Timeline')">
                            <x-search.result-table
                                :title="__('Plotlines')"
                                :rows="$results->plotlines"
                                edit-route="plotlines.edit"
                            />
                            <x-search.result-table
                                :title="__('Events')"
                                :rows="$results->events"
                                edit-route="events.edit"
                                name-field="title"
                            />
                        </x-search.section>
                    @endif

                    {{-- Story — Acts, Chapters, Scenes. --}}
                    @if ($results->hasStoryMatches())
                        <x-search.section :title="__('Story')">
                            <x-search.result-table
                                :title="__('Acts')"
                                :rows="$results->acts"
                                edit-route="acts.edit"
                            />
                            <x-search.result-table
                                :title="__('Chapters')"
                                :rows="$results->chapters"
                                edit-route="chapters.edit"
                            />
                            <x-search.result-table
                                :title="__('Scenes')"
                                :rows="$results->scenes"
                                edit-route="scenes.edit"
                            />
                        </x-search.section>
                    @endif

                    {{-- Codex — one table per CodexEntryType. All share codex.edit. --}}
                    @if ($results->hasCodexMatches())
                        <x-search.section :title="__('Codex')">
                            <x-search.result-table
                                :title="__('Characters')"
                                :rows="$results->characters"
                                edit-route="codex.edit"
                            />
                            <x-search.result-table
                                :title="__('Locations')"
                                :rows="$results->locations"
                                edit-route="codex.edit"
                            />
                            <x-search.result-table
                                :title="__('Organizations')"
                                :rows="$results->organizations"
                                edit-route="codex.edit"
                            />
                        </x-search.section>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
