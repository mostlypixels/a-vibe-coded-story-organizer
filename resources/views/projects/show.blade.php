<x-app-layout>
    {{-- The project's words are the Goals card's Total row, and editing it is
         the sidebar's Actions card — as on every other edit screen. Neither
         repeats here. --}}
    <div class="mb-6">
        <x-heading level="1">{{ $project->name }}</x-heading>
    </div>

    {{-- Where the author left off. First on the page: a writer opens the
         dashboard to get back to work. Two lists only — scenes are the daily
         work and each row names its act and chapter, and the codex mixes its
         types. The top menu reaches everything else. --}}
    <div class="mb-6 grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-9 grid gap-6 md:grid-cols-2">
            <x-recent-list
                :title="__('Recent scenes')"
                :items="$recentScenes"
                :all-url="route('projects.story.home', $project)"
                :all-label="__('View the story')"
                :noun="__('scenes')"
            />

            <x-recent-list
                :title="__('Recent codex entries')"
                :items="$recentCodexEntries"
                :all-url="route('projects.codex.home', $project)"
                :all-label="__('View the codex')"
                :noun="__('codex entries')"
                show-covers
            />
        </div>

        {{-- The same Actions card the edit pages open their sidebar with, so
             the one action this page has sits where a writer looks. --}}
        <div class="lg:col-span-3">
            <x-card :title="__('Actions')">
                <x-button :href="route('projects.edit', $project)" variant="primary" icon="tabler-pencil" class="w-full">
                    {{ __('Edit Project') }}
                </x-button>
            </x-card>
        </div>
    </div>

    {{-- The site's 9-3 split, as on the edit pages: the chart reads as the
         page's main content, the goals as its sidebar. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-9">
            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-4">
                        <x-heading level="3">{{ __('Progress') }}</x-heading>
                        <a href="{{ route('projects.progress', $project) }}" class="text-sm text-content-muted hover:text-content">
                            {{ __('View history →') }}
                        </a>
                    </div>
                </x-slot:header>

                <x-word-count-chart :series="$progressSeries" :daily-goal="$project->daily_word_goal" variant="full" />
            </x-card>
        </div>

        <div class="lg:col-span-3 space-y-6">
            <x-card>
                <x-slot:header>
                    <x-heading level="3">{{ __('Goals') }}</x-heading>
                </x-slot:header>

                {{-- A null goal drops its row entirely — no bar, no "of ∞" —
                     see expanded/ui.md, "A null goal drops its row entirely". --}}
                <div class="space-y-4">
                    @if ($project->daily_word_goal !== null)
                        <x-progress-bar :label="__('Today')" :value="$writtenToday" :goal="$project->daily_word_goal" />
                    @endif

                    {{-- Without a goal there is no bar, but the project's own
                         total still belongs here: it is the only place the
                         dashboard states how long the book is. --}}
                    @if ($project->total_word_goal !== null)
                        <x-progress-bar :label="__('Total')" :value="$wordCount" :goal="$project->total_word_goal" />
                    @else
                        <div class="flex items-baseline justify-between gap-4">
                            <span class="text-sm font-medium text-content">{{ __('Total') }}</span>
                            <x-word-count :count="$wordCount" variant="muted" />
                        </div>
                    @endif

                    @if ($project->daily_word_goal === null && $project->total_word_goal === null)
                        <p class="text-sm text-content-muted">
                            {{ __('Set a daily or total word goal on the project form to track progress here.') }}
                        </p>
                    @endif
                </div>

                {{-- Today counts once it is met, so a writer who has not started
                     yet still sees yesterday's streak, not a zero. --}}
                @if ($project->daily_word_goal !== null)
                    <div class="mt-4 flex items-center gap-3 rounded-lg bg-surface-raised px-4 py-3">
                        <x-tabler-flame class="h-6 w-6 shrink-0 text-content-muted" aria-hidden="true" />
                        <div>
                            <p class="text-lg font-semibold text-content">
                                {{ trans_choice('{0}No streak yet|{1}:count day in a row|[2,*]:count days in a row', $writingStreak, ['count' => number_format($writingStreak)]) }}
                            </p>
                            <p class="text-xs text-content-subtle">
                                {{ $writingStreak > 0 ? __('Days meeting the daily goal') : __('Meet the daily goal to start one') }}
                            </p>
                        </div>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
