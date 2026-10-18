{{--
    Left panel: scene search and the recent scenes of the project.
    An empty search shows the recent list. A query shows results instead.
    Rows and results are real links, so the leave guard flushes a pending save first.
--}}
@php
    $currentUrl = route('scenes.write', $scene);
@endphp

<div
    class="space-y-3"
    x-data="editorSceneSearch({
        candidatesUrl: @js(route('projects.link-targets', $project)),
        sceneUrl: @js(route('scenes.write', ['scene' => '__SCENE__'])),
        failureMessage: @js(__('The search failed. Check your connection and try again.')),
    })"
    x-on:keydown.escape="query.trim() !== '' && $event.stopPropagation(); onEscape()"
    data-editor-scene-search
>
    <div>
        <x-input-label for="editor-scene-query" :value="__('Search scenes')" class="sr-only" />
        <x-text-input
            id="editor-scene-query"
            type="search"
            autocomplete="off"
            class="block w-full"
            :placeholder="__('Search scenes')"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="editor-scene-results"
            x-bind:aria-expanded="(!showingRecent()).toString()"
            x-bind:aria-activedescendant="results.length ? 'editor-scene-option-' + highlighted : null"
            x-model="query"
            x-on:input="onInput()"
            x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter.prevent="openHighlighted()"
        />
    </div>

    <div x-show="!showingRecent()" style="display: none;">
        <ul id="editor-scene-results" x-ref="results" role="listbox" class="space-y-1" aria-label="{{ __('Results') }}" x-bind:aria-busy="searching">
            <template x-for="(candidate, index) in results" x-bind:key="candidate.id">
                <li role="presentation">
                    <a
                        role="option"
                        x-bind:id="'editor-scene-option-' + index"
                        x-bind:href="urlFor(candidate)"
                        x-bind:aria-selected="(index === highlighted).toString()"
                        x-bind:class="index === highlighted ? 'bg-accent-surface text-accent-content' : 'text-content hover:bg-neutral'"
                        class="block rounded-md px-2 py-1 text-sm"
                        x-text="candidate.label"
                        x-on:mousemove="highlighted = index"
                    ></a>
                </li>
            </template>
        </ul>
        <p class="px-2 text-sm text-content-muted" x-show="!searching && !error && results.length === 0" style="display: none;">{{ __('Nothing found.') }}</p>
        <p class="text-sm text-danger" x-show="error" style="display: none;" role="alert" x-text="error"></p>
    </div>

    <div x-show="showingRecent()" data-editor-recent>
        <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-content-subtle">{{ __('Recent scenes') }}</h3>

        <ul class="divide-y divide-border">
            @foreach ($recentScenes as $item)
                @php
                    $isCurrent = $item->url === $currentUrl;
                @endphp
                <li>
                    <a
                        href="{{ $item->url }}"
                        @if ($isCurrent) aria-current="page" @endif
                        @class([
                            'block rounded-sm px-2 py-2 hover:bg-neutral',
                            'bg-accent-surface text-accent-content' => $isCurrent,
                        ])
                    >
                        <span class="block truncate text-sm font-medium text-content">{{ $item->label }}</span>
                        @if ($item->contextSegments)
                            <span class="flex min-w-0 items-center gap-1 text-xs text-content-subtle">
                                @foreach ($item->contextSegments as $segment)
                                    @unless ($loop->first)
                                        <x-tabler-chevron-right class="h-3 w-3 shrink-0 opacity-50" aria-hidden="true" />
                                    @endunless
                                    <span class="{{ $loop->last ? 'truncate' : 'shrink-0 whitespace-nowrap' }}">{{ $segment }}</span>
                                @endforeach
                            </span>
                        @endif
                        <time
                            datetime="{{ $item->updatedAt->toIso8601String() }}"
                            title="{{ $item->updatedAt->format('d F Y H:i') }}"
                            class="block text-xs text-content-muted"
                        >{{ $item->updatedAt->diffForHumans() }}</time>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
