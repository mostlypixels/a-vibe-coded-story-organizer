<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Story Overview') }}
    </x-page-heading>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <x-heading level="1">{{ __('Story Overview') }}</x-heading>
            <x-word-count :count="$wordCount" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Left column: Table of Contents, sticky so it stays in view while the
                 (potentially very long) act/chapter/scene content scrolls beside it. --}}
            <div class="lg:col-span-3">
                <x-collapsible-card :title="__('Table of Contents')" class="lg:sticky lg:top-6">
                    <div class="space-y-3">
                        @foreach ($acts as $act)
                            <div>
                                <a href="#act-{{ $act->id }}" class="font-semibold text-content hover:text-content-muted">
                                    {{ __('Act :number', ['number' => $numbering->act($act)]) }} &mdash; {{ $act->name }}
                                </a>

                                @if ($act->chapters->isNotEmpty())
                                    <ul class="mt-1 ml-4 space-y-1">
                                        @foreach ($act->chapters as $chapter)
                                            <li>
                                                <a href="#chapter-{{ $chapter->id }}" class="text-sm text-content-muted hover:text-content">
                                                    {{ __('Chapter :number', ['number' => $numbering->chapter($chapter)]) }} &mdash; {{ $chapter->name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-collapsible-card>
            </div>

            {{-- Right column: the full act/chapter/scene content. --}}
            <div class="lg:col-span-9 space-y-10">
                @forelse ($acts as $act)
                    <div class="space-y-6">
                        {{-- The coloured bar is this wrapper, not the heading, so the count can sit
                             *beside* the <h2> instead of inside it. A heading's own text is its
                             accessible name: with the count nested in, screen-reader heading
                             navigation announced "Act 1 — Melusine's Youth 490 words", and the act
                             was renamed every time the writer added a sentence. --}}
                        <div class="flex items-center justify-between gap-4 text-nav-content bg-nav rounded-md px-4 py-2">
                            <h2 id="act-{{ $act->id }}" class="text-2xl font-bold scroll-mt-16">
                                {{ __('Act :number', ['number' => $numbering->act($act)]) }} &mdash; {{ $act->name }}
                            </h2>
                            {{-- Scenes are already eager-loaded (StoryController::index), so this
                                 ->sum() over the loaded chapters/scenes collections is free — no query. --}}
                            <x-word-count
                                :count="$act->chapters->sum(fn ($chapter) => $chapter->scenes->sum('word_count'))"
                                variant="inline"
                                class="shrink-0 text-base font-normal"
                            />
                        </div>

                        @forelse ($act->chapters as $chapter)
                            <x-collapsible-card>
                                <x-slot:header>
                                    <div class="flex items-center justify-between gap-4">
                                        <h3 id="chapter-{{ $chapter->id }}" class="text-xl font-semibold text-content scroll-mt-16">
                                            {{ __('Chapter :number', ['number' => $numbering->chapter($chapter)]) }} &mdash; {{ $chapter->name }}
                                        </h3>
                                        {{-- $chapter->scenes is already eager-loaded, so this ->sum() is free. --}}
                                        <x-word-count :count="$chapter->scenes->sum('word_count')" class="shrink-0" />
                                    </div>
                                </x-slot:header>

                                <div class="space-y-4">
                                    @forelse ($chapter->scenes as $scene)
                                        <section x-data="{ open: true }" @unless($scene->event) title="{{ __('This scene has no “happens during” event yet.') }}" @endunless class="space-y-2 pb-4 border-b border-border last:border-b-0 last:pb-0 {{ $scene->event ? '' : 'border-l-4 border-l-danger pl-4' }}">
                                            <div class="flex items-center justify-between">
                                                <button type="button" @click="open = ! open" class="flex items-center gap-2 text-sm font-light text-content-muted">
                                                    <x-tabler-chevron-down class="h-4 w-4 text-content-muted transition-transform" x-bind:class="{ 'rotate-180': open }" />
                                                    <span data-scene-number class="text-content-subtle">{{ $numbering->scene($scene) }}.</span>
                                                    {{ $scene->name }}
                                                </button>

                                                <div class="flex items-center gap-2">
                                                    @if ($scene->event)
                                                        <span class="text-xs text-content-muted">{{ __('Set during') }} {{ $scene->event->title }}</span>
                                                    @else
                                                        <span title="{{ __('This scene has no “happens during” event yet.') }}" class="inline-flex items-center rounded-md border border-danger px-2 py-0.5 text-xs font-medium text-danger-surface-content">{{ __('Unassigned') }}</span>
                                                    @endif
                                                    <x-scene-status-badge :status="$scene->status" />
                                                    {{-- Reordering here is AJAX (moveScene below re-evaluates which
                                                         buttons are at the ends and toggles `disabled` in place), so
                                                         these are plain buttons rather than <x-icon-move-button>'s
                                                         PATCH form. The `ghost` variant styles its own disabled
                                                         state, so the JS toggle restyles them with no extra work. --}}
                                                    <x-icon-button
                                                        type="button"
                                                        variant="ghost"
                                                        icon="chevron-up"
                                                        :label="__('Move up')"
                                                        data-move="up"
                                                        onclick="moveScene(this, '{{ route('scenes.move-up', $scene) }}', 'up')"
                                                        :disabled="$loop->first"
                                                    />
                                                    <x-icon-button
                                                        type="button"
                                                        variant="ghost"
                                                        icon="chevron-down"
                                                        :label="__('Move down')"
                                                        data-move="down"
                                                        onclick="moveScene(this, '{{ route('scenes.move-down', $scene) }}', 'down')"
                                                        :disabled="$loop->last"
                                                    />
                                                    <x-icon-edit-link :href="route('scenes.edit', $scene)" />
                                                </div>
                                            </div>

                                            <div x-show="open" x-transition class="prose prose-sm max-w-none text-content-muted text-justify text-[0.8125rem] [&_p]:my-4">
                                                {!! $scene->renderedContents !!}
                                            </div>
                                        </section>
                                    @empty
                                        <p class="text-sm text-content-muted">{{ __('No scenes in this chapter yet.') }}</p>
                                    @endforelse
                                </div>
                            </x-collapsible-card>
                        @empty
                            <p class="text-sm text-content-muted">{{ __('No chapters in this act yet.') }}</p>
                        @endforelse
                    </div>
                @empty
                    <p class="text-center text-content-muted">{{ __('No acts yet.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
