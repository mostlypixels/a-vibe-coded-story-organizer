<x-app-layout>
    <x-page-heading>
        {{ $book->displayName() }} &mdash; {{ __('Story Overview') }}
    </x-page-heading>

    <div class="space-y-6">
        <div class="flex items-center justify-end gap-4">
            @can('update', $book->project)
                <x-story-mode-switch :book="$book" :mode="$book->overview_render_mode" />
            @endcan
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
                            <x-story-chapter :chapter="$chapter" :numbering="$numbering" />
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
