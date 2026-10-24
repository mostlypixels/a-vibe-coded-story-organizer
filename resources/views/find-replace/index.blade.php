<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Find and replace') }}
    </x-page-heading>

    <div class="space-y-6">
        <form method="GET" action="{{ route('projects.replace.index', $project) }}" class="bg-surface-raised shadow-xs rounded-lg p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <x-input-label for="find" :value="__('Find')" />
                    <x-text-input
                        id="find"
                        type="text"
                        name="find"
                        :value="$query->find"
                        :maxlength="\App\Support\FindReplaceQuery::MAX_FIND_LENGTH"
                        class="block w-full"
                        autofocus
                    />
                    <x-input-error :messages="$errors->get('find')" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="replace" :value="__('Replace with')" />
                    <x-text-input
                        id="replace"
                        type="text"
                        name="replace"
                        :value="$query->replace"
                        :maxlength="\App\Support\FindReplaceQuery::MAX_REPLACE_LENGTH"
                        class="block w-full"
                    />
                    <x-input-error :messages="$errors->get('replace')" />
                    <p class="text-xs text-content-muted">{{ __('Leave empty to delete the matches.') }}</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2">
                {{-- The hidden 0 tells an unticked box from a first visit, where both default to on. --}}
                <input type="hidden" name="match_case" value="0" />
                <label class="inline-flex items-center gap-2 text-sm text-content-muted">
                    <input
                        type="checkbox"
                        name="match_case"
                        value="1"
                        @checked($query->matchCase)
                        class="rounded border-border-strong text-primary focus:ring-focus"
                    />
                    {{ __('Match case') }}
                </label>

                <input type="hidden" name="whole_word" value="0" />
                <label class="inline-flex items-center gap-2 text-sm text-content-muted">
                    <input
                        type="checkbox"
                        name="whole_word"
                        value="1"
                        @checked($query->wholeWord)
                        class="rounded border-border-strong text-primary focus:ring-focus"
                    />
                    {{ __('Whole words') }}
                </label>
            </div>

            <x-search.narrow-panel :project="$project" :books="$books" :book="$book" :chapters="$chapters" :acts="$acts" :scope="$scope" :domains="false" />

            <div class="flex items-center gap-4">
                <x-button type="submit" variant="primary">{{ __('Preview') }}</x-button>
                <a href="{{ route('projects.search.index', ['project' => $project, 'q' => $query->find] + $scope->toQuery()) }}" class="text-sm text-link underline hover:text-link-hover">
                    {{ __('Search instead') }}
                </a>
            </div>
        </form>

        @if (session('replace_result'))
            @php($result = session('replace_result'))
            <div class="space-y-3" data-replace-result>
                <x-alert :variant="$result['replaced'] > 0 ? 'success' : 'info'" dismissible>
                    {{ trans_choice('{0} Nothing was replaced.|{1} Replaced 1 match in :scenes.|[2,*] Replaced :count matches in :scenes.', $result['replaced'], [
                        'scenes' => trans_choice('{1} 1 scene|[2,*] :count scenes', $result['scenes'], ['count' => $result['scenes']]),
                    ]) }}
                </x-alert>

                @if ($result['skipped'] !== [])
                    <x-alert variant="warning" data-skipped-scenes>
                        <p>{{ __('These scenes changed after the preview, so they were skipped. Preview again to see their matches.') }}</p>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($result['skipped'] as $skipped)
                                <li>
                                    <a href="{{ route('scenes.show', $skipped['id']) }}" target="_blank" rel="noopener" class="underline">{{ $skipped['name'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif
            </div>
        @endif

        @if ($preview !== null)
            @if ($scopeSummary !== null)
                <div class="bg-surface-raised shadow-xs rounded-lg px-4 py-3 text-sm text-content-muted">
                    <p>{{ __('Filtered to :summary.', ['summary' => $scopeSummary]) }}</p>
                </div>
            @endif

            @if ($preview->isEmpty())
                <div class="bg-surface-raised shadow-xs rounded-lg px-6 py-10 text-center text-content-muted">
                    <p class="font-medium">{{ __('No matches for “:find”.', ['find' => $query->find]) }}</p>
                    <p class="mt-1 text-sm">{{ __('Try another word, or switch off “Match case” or “Whole words”.') }}</p>
                </div>
            @else
                {{-- `hidden` + `:hidden="false"` shows the selection controls only when Alpine runs. Without it, the form posts every match. --}}
                <div
                    class="space-y-4"
                    data-preview
                    x-data="findReplace(@js($preview->selectAll()), @js(\App\Support\ScriptTranslations::findReplace()))"
                >
                    <p class="text-sm text-content-muted" data-match-count="{{ $preview->matchCount() }}">
                        {{ trans_choice('{1} :count match in :scenes.|[2,*] :count matches in :scenes.', $preview->matchCount(), [
                            'scenes' => trans_choice('{1} 1 scene|[2,*] :count scenes', count($preview->scenes), ['count' => count($preview->scenes)]),
                        ]) }}
                    </p>

                    @if ($preview->capped)
                        <x-alert variant="warning">
                            {{ __('Showing the first :count matches. Narrow the scope to see the rest.', ['count' => $maxMatches]) }}
                        </x-alert>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('projects.replace.store', $project) }}"
                        x-ref="form"
                        x-on:submit.prevent="request()"
                        class="sticky top-0 z-10 bg-surface-raised shadow-sm rounded-lg px-4 py-3 space-y-3"
                        data-apply-form
                        data-selection-bar
                    >
                        @csrf
                        <input type="hidden" name="find" value="{{ $query->find }}" />
                        <input type="hidden" name="replace" value="{{ $query->replace }}" />
                        <input type="hidden" name="match_case" value="{{ (int) $query->matchCase }}" />
                        <input type="hidden" name="whole_word" value="{{ (int) $query->wholeWord }}" />
                        @foreach ($scope->toQuery() as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}" />
                        @endforeach
                        <input type="hidden" name="selection" x-ref="selection" value="{{ json_encode($preview->selectAll()) }}" />
                        <x-input-error :messages="$errors->get('selection')" />

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm text-content-muted" hidden x-bind:hidden="false" x-text="counterText" aria-live="polite" data-selection-counter></p>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex flex-wrap items-center gap-2" hidden x-bind:hidden="false">
                                    <x-button type="button" variant="secondary" size="sm" x-on:click="selectAll()">{{ __('Select all') }}</x-button>
                                    <x-button type="button" variant="secondary" size="sm" x-on:click="selectNone()">{{ __('Select none') }}</x-button>
                                    <x-button type="button" variant="ghost" size="sm" x-on:click="setAllOpen(true)">{{ __('Unfold all') }}</x-button>
                                    <x-button type="button" variant="ghost" size="sm" x-on:click="setAllOpen(false)">{{ __('Fold all') }}</x-button>
                                </span>
                                <x-button type="submit" variant="primary" x-bind:disabled="selectedCount() === 0" x-text="replaceLabel()" data-replace-selected>
                                    {{ trans_choice('{1} Replace 1 match|[2,*] Replace :count matches', $preview->matchCount(), ['count' => $preview->matchCount()]) }}
                                </x-button>
                            </div>
                        </div>

                        <div
                            x-show="pending !== null"
                            style="display: none"
                            role="alert"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-warning bg-warning-surface px-3 py-2 text-sm text-warning-surface-content"
                            data-confirm-panel
                        >
                            <p class="font-medium" x-text="confirmText"></p>
                            <span class="flex items-center gap-2">
                                <x-button type="button" variant="secondary" size="sm" x-on:click="cancel()">{{ __('Cancel') }}</x-button>
                                <x-button type="button" variant="primary" size="sm" x-on:click="accept()" data-confirm-replace>{{ __('Replace') }}</x-button>
                            </span>
                        </div>
                    </form>

                    @foreach ($preview->scenes as $group)
                        @php($scene = $group->scene)
                        <details
                            @if ($preview->startsOpen()) open @endif
                            class="bg-surface-raised shadow-xs rounded-lg overflow-hidden"
                            data-scene-id="{{ $scene->id }}"
                            data-scene-hash="{{ $group->hash }}"
                        >
                            <summary class="flex flex-wrap items-center justify-between gap-2 cursor-pointer select-none px-4 py-3">
                                <span class="flex min-w-0 items-start gap-3 font-medium text-content">
                                    <input
                                        type="checkbox"
                                        hidden
                                        x-bind:hidden="false"
                                        x-effect="$el.checked = sceneState({{ $scene->id }}) === 'all'; $el.indeterminate = sceneState({{ $scene->id }}) === 'some'"
                                        x-bind:data-state="sceneState({{ $scene->id }})"
                                        x-on:change="toggleScene({{ $scene->id }})"
                                        aria-label="{{ __('Replace every match in :scene', ['scene' => $scene->name]) }}"
                                        class="mt-1 rounded border-border-strong text-primary focus:ring-focus"
                                        data-scene-checkbox
                                    />
                                    <span>
                                        @if ($group->book->hasOwnName())
                                            <span class="text-content-muted">{{ $group->book->displayName() }} /</span>
                                        @endif
                                        <span class="text-content-muted">{{ $scene->chapter->act->name }} / {{ $scene->chapter->name }} /</span>
                                        {{ $scene->name }}
                                    </span>
                                </span>
                                <span class="flex flex-wrap items-center gap-3 text-sm text-content-muted">
                                    <span>{{ trans_choice('{1} 1 match|[2,*] :count matches', count($group->matches), ['count' => count($group->matches)]) }}</span>
                                    <a href="{{ route('scenes.show', $scene) }}" target="_blank" rel="noopener" class="text-link underline hover:text-link-hover">
                                        {{ __('Open scene') }}
                                    </a>
                                    <x-button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        hidden
                                        x-bind:hidden="false"
                                        x-bind:disabled="selectedCount({{ $scene->id }}) === 0"
                                        x-on:click.prevent="request({{ $scene->id }})"
                                        data-replace-scene
                                    >{{ __('Replace in this scene') }}</x-button>
                                </span>
                            </summary>

                            <ul class="divide-y divide-border border-t border-border">
                                @foreach ($group->matches as $match)
                                    <li class="px-4 py-2">
                                        <label class="flex items-start gap-3 text-sm text-content">
                                            <input
                                                type="checkbox"
                                                hidden
                                                x-bind:hidden="false"
                                                x-effect="$el.checked = isSelected({{ $scene->id }}, {{ $match->index }})"
                                                x-on:change="toggle({{ $scene->id }}, {{ $match->index }})"
                                                data-scene-id="{{ $scene->id }}"
                                                data-match-index="{{ $match->index }}"
                                                aria-label="{{ __('Replace this match') }}"
                                                class="mt-1 rounded border-border-strong text-primary focus:ring-focus"
                                            />
                                            <span class="min-w-0 break-words">{{ $match->before }}<del class="bg-danger-surface text-danger-surface-content line-through">{{ $match->found }}</del><ins class="bg-success-surface text-success-surface-content no-underline">{{ $query->replace }}</ins>{{ $match->after }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
