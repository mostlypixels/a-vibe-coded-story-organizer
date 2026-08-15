<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Story Overview') }}
    </x-page-heading>

    <div class="space-y-6">
        <div class="flex items-center justify-end gap-4">
            @can('update', $project)
                <x-story-mode-switch :project="$project" :mode="$project->overview_render_mode" :chapter-id="$currentChapter?->id" />
            @endcan
            <x-word-count :count="$wordCount" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Left column: whole-book Table of Contents, sticky so it stays in view
                 while the current chapter scrolls beside it. Every act and chapter is
                 listed even though only one chapter's scenes are loaded. Links carry
                 the target chapter's id — the act link points at its first chapter —
                 plus the `#chapter-{id}` fragment so the target scrolls into view. --}}
            <div class="lg:col-span-3">
                <x-collapsible-card :title="__('Table of Contents')" class="lg:sticky lg:top-6">
                    <div class="space-y-3">
                        @foreach ($tocActs as $act)
                            <div>
                                @if ($act->chapters->isNotEmpty())
                                    @php($firstChapter = $act->chapters->first())
                                    <a href="{{ route('projects.story.overview', ['project' => $project, 'chapter' => $firstChapter->id]) }}#chapter-{{ $firstChapter->id }}" class="font-semibold text-content hover:text-content-muted">
                                        {{ __('Act :number', ['number' => $numbering->act($act)]) }} &mdash; {{ $act->name }}
                                    </a>

                                    <ul class="mt-1 ml-4 space-y-1">
                                        @foreach ($act->chapters as $chapter)
                                            <li>
                                                <a href="{{ route('projects.story.overview', ['project' => $project, 'chapter' => $chapter->id]) }}#chapter-{{ $chapter->id }}" class="text-sm text-content-muted hover:text-content">
                                                    {{ __('Chapter :number', ['number' => $numbering->chapter($chapter)]) }} &mdash; {{ $chapter->name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="font-semibold text-content">
                                        {{ __('Act :number', ['number' => $numbering->act($act)]) }} &mdash; {{ $act->name }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-collapsible-card>
            </div>

            {{-- Right column: the current chapter only, under an always-shown header for
                 its act so a mid-act chapter page keeps the reader's place. The act total
                 is the whole act's, from the aggregate — not the one loaded chapter. The
                 pager sits above and below the chapter card so it's reachable without
                 scrolling back up on a long chapter. --}}
            <div class="lg:col-span-9 space-y-10">
                @if ($currentChapter)
                    <div class="space-y-6">
                        <x-chapter-pager :project="$project" :previous="$previousChapter" :next="$nextChapter" :numbering="$numbering" />

                        <div class="flex items-center justify-between gap-4 text-nav-content bg-nav rounded-md px-4 py-2">
                            <h2 id="act-{{ $currentChapter->act->id }}" class="text-2xl font-bold scroll-mt-16">
                                {{ __('Act :number', ['number' => $numbering->act($currentChapter->act)]) }} &mdash; {{ $currentChapter->act->name }}
                            </h2>
                            <x-word-count
                                :count="$actWordCount"
                                variant="inline"
                                class="shrink-0 text-base font-normal"
                            />
                        </div>

                        <x-story-chapter :chapter="$currentChapter" :numbering="$numbering" />

                        <x-chapter-pager :project="$project" :previous="$previousChapter" :next="$nextChapter" :numbering="$numbering" />
                    </div>
                @else
                    <p class="text-center text-content-muted">{{ __('No acts yet.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
