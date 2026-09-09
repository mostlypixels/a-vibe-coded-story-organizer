<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Search') }}
    </x-page-heading>

    <div class="space-y-6">
        <form method="GET" action="{{ route('projects.search.index', $project) }}" class="bg-surface-raised shadow-xs rounded-lg p-6 space-y-4">
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
                <legend class="block font-medium text-sm text-content-muted">{{ __('Match') }}</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2">
                    @foreach (\App\Enums\SearchMode::cases() as $searchMode)
                        <label class="inline-flex items-center gap-2 text-sm text-content-muted">
                            <input
                                type="radio"
                                name="mode"
                                value="{{ $searchMode->value }}"
                                @checked($mode === $searchMode)
                                class="border-border-strong text-primary focus:ring-focus"
                            />
                            {{ $searchMode->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <x-search.narrow-panel :project="$project" :books="$books" :book="$book" :chapters="$chapters" :scope="$scope" />
        </form>

        @if ($results !== null)
            @if ($scope->isNarrowed())
                @php
                    $summaryParts = \App\Support\SearchScopeSummary::parts($scope, $book, $chapters);
                @endphp
                <div class="bg-surface-raised shadow-xs rounded-lg px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm text-content-muted">
                    <p>{{ __('Filtered to :summary.', ['summary' => implode(', ', $summaryParts)]) }}</p>
                    <a
                        href="{{ route('projects.search.index', ['project' => $project, 'q' => $query, 'mode' => $mode->value]) }}"
                        class="text-link underline hover:text-link-hover shrink-0"
                    >
                        {{ __('Clear') }}
                    </a>
                </div>
            @endif

            @if ($results->isEmpty())
                <div class="bg-surface-raised shadow-xs rounded-lg px-6 py-10 text-center text-content-muted">
                    <p class="font-medium text-content-muted">
                        {{ __('No results match “:query”.', ['query' => $query]) }}
                    </p>
                    <p class="mt-1 text-sm">{{ __('Try a different word or switch the match mode.') }}</p>
                </div>
            @else
                <div class="space-y-8">
                    @if ($scope->bookId !== null)
                        <p class="text-sm text-content-muted">
                            {{ __('Plotlines, events and the codex belong to the whole project. Clear the book filter to search them.') }}
                        </p>
                    @endif

                    @if ($results->hasTimelineMatches())
                        <x-search.section :title="__('Timeline')">
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Plotlines" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Events" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                        </x-search.section>
                    @endif

                    @if ($results->hasStoryMatches())
                        <x-search.section :title="__('Story')">
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Acts" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Chapters" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Scenes" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                        </x-search.section>
                    @endif

                    @if ($results->hasCodexMatches())
                        <x-search.section :title="__('Codex')">
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Characters" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Locations" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                            <x-search.result-table :domain="\App\Enums\SearchDomain::Organizations" :results="$results" :project="$project" :query="$query" :mode="$mode" :scope="$scope" />
                        </x-search.section>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
