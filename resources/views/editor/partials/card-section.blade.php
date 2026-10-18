{{--
    One section of the right panel: Codex or Notes. A search field, its results, then the cards.
    Needs: $type ('codex' or 'note'), $heading, $cards, $cardUrl and $viewUrl (templates with __ID__).
    $section and $collapsed: the `editorLayout` section key and its stored state.
    The `<details>` reports each toggle with an event: a direct call would run in the `editorCards` scope.
--}}
@php
    $queryId = 'editor-'.$type.'-query';
    $resultsId = 'editor-'.$type.'-results';
@endphp

<section
    aria-labelledby="editor-{{ $type }}-heading"
    x-data="editorCards({
        type: @js($type),
        candidatesUrl: @js(route('projects.link-targets', $project)),
        cardUrl: @js($cardUrl),
        viewUrl: @js($viewUrl),
        saveUrl: @js(route('scenes.editor-cards.update', $scene)),
        closeLabel: @js(__('Close :name')),
        viewLabel: @js(__('Open :name in a new tab')),
        searchFailureMessage: @js(__('The search failed. Check your connection and try again.')),
        loadFailureMessage: @js(__('The card could not load. Check your connection and try again.')),
        saveFailureMessage: @js(__('The card state was not saved.')),
    })"
    x-on:keydown.escape="query.trim() !== '' && $event.stopPropagation(); clearSearch()"
    data-editor-section="{{ $type }}"
>
    <details
        class="group/section space-y-3"
        @unless ($collapsed) open @endunless
        x-on:toggle="$dispatch('editor-section-toggle', { section: @js($section), open: $el.open })"
        data-editor-section-details="{{ $section }}"
    >
        <summary class="flex cursor-pointer select-none list-none items-center gap-1 [&::-webkit-details-marker]:hidden">
            <x-tabler-chevron-down class="h-3.5 w-3.5 shrink-0 -rotate-90 text-content-subtle transition-transform group-open/section:rotate-0" aria-hidden="true" />
            <h3 id="editor-{{ $type }}-heading" class="text-xs font-semibold uppercase tracking-wide text-content-subtle">{{ $heading }}</h3>
        </summary>

        <div>
            <x-input-label :for="$queryId" :value="__('Search :section', ['section' => $heading])" class="sr-only" />
            <x-text-input
                :id="$queryId"
                type="search"
                autocomplete="off"
                class="block w-full"
                :placeholder="__('Search :section', ['section' => $heading])"
                role="combobox"
                aria-autocomplete="list"
                :aria-controls="$resultsId"
                x-ref="query"
                x-bind:aria-expanded="(query.trim() !== '').toString()"
                x-bind:aria-activedescendant="results.length ? '{{ $resultsId }}-' + highlighted : null"
                x-model="query"
                x-on:input="onInput()"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="pickHighlighted()"
            />
        </div>

        <div x-show="query.trim() !== ''" style="display: none;">
            <ul id="{{ $resultsId }}" role="listbox" class="space-y-1" aria-label="{{ __('Results') }}" x-bind:aria-busy="searching">
                <template x-for="(candidate, index) in results" x-bind:key="candidate.id">
                    <li
                        role="option"
                        x-bind:id="'{{ $resultsId }}-' + index"
                        x-bind:aria-selected="(index === highlighted).toString()"
                        x-bind:class="index === highlighted ? 'bg-accent-surface text-accent-content' : 'text-content hover:bg-neutral'"
                        class="cursor-pointer rounded-md px-2 py-1 text-sm"
                        x-text="candidate.label"
                        x-on:mousemove="highlighted = index"
                        x-on:click="pick(candidate)"
                    ></li>
                </template>
            </ul>
            <p class="px-2 text-sm text-content-muted" x-show="!searching && !error && results.length === 0" style="display: none;">{{ __('Nothing found.') }}</p>
            <p class="text-sm text-danger" x-show="error" style="display: none;" role="alert" x-text="error"></p>
        </div>

        <p class="text-sm text-content-muted" x-show="loading" style="display: none;" role="status">{{ __('Loading…') }}</p>
        <p class="text-sm text-danger" x-show="loadError" style="display: none;" role="alert" x-text="loadError" data-card-load-error></p>
        <p class="text-sm text-danger" x-show="saveError" style="display: none;" role="alert" x-text="saveError" data-card-save-error></p>

        <div class="space-y-2" x-ref="cards" x-on:click="onCardClick($event)" data-editor-cards>
            @foreach ($cards as $card)
                <x-editor-card
                    :type="$card['type']"
                    :id="$card['id']"
                    :title="$card['title']"
                    :state="$card['state']->value"
                    :view-url="$card['viewUrl']"
                >
                    @if ($type === 'codex')
                        @include('editor.partials.codex-card', ['row' => $card['row'], 'undetermined' => $scene->event === null])
                    @else
                        @include('editor.partials.note-card', ['note' => $card['note']])
                    @endif
                </x-editor-card>
            @endforeach
        </div>

        <template x-ref="template">
            <x-editor-card :type="$type" />
        </template>
    </details>
</section>
