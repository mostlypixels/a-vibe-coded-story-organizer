<x-app-layout>
    <x-page-heading>
        {{ $book->displayName() }} &mdash; {{ __('Story Overview') }}
    </x-page-heading>

    <div class="space-y-6">
        <div class="flex items-center justify-end gap-4">
            @can('update', $book->project)
                <x-story-mode-switch :book="$book" :mode="$book->overview_render_mode" :chapter-id="$currentChapter?->id" />
            @endcan
            <x-word-count :count="$wordCount" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-3">
                <x-collapsible-card :title="__('Table of Contents')" class="lg:sticky lg:top-6">
                    <div class="space-y-3">
                        @foreach ($tocActs as $act)
                            <div>
                                @if ($act->chapters->isNotEmpty())
                                    @php($firstChapter = $act->chapters->first())
                                    <a href="{{ route('books.story.overview', ['book' => $book, 'chapter' => $firstChapter->id]) }}#chapter-{{ $firstChapter->id }}" class="font-semibold text-content hover:text-content-muted">
                                        {{ __('Act :number', ['number' => $numbering->act($act)]) }} &mdash; {{ $act->name }}
                                    </a>

                                    <ul class="mt-1 ml-4 space-y-1">
                                        @foreach ($act->chapters as $chapter)
                                            <li>
                                                <a href="{{ route('books.story.overview', ['book' => $book, 'chapter' => $chapter->id]) }}#chapter-{{ $chapter->id }}" class="text-sm text-content-muted hover:text-content">
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

            <div class="lg:col-span-9 space-y-10">
                @if ($currentChapter)
                    <div class="space-y-6">
                        <x-chapter-pager :book="$book" :previous="$previousChapter" :next="$nextChapter" :numbering="$numbering" />

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

                        <x-chapter-pager :book="$book" :previous="$previousChapter" :next="$nextChapter" :numbering="$numbering" />
                    </div>
                @else
                    <p class="text-center text-content-muted">{{ __('No acts yet.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
