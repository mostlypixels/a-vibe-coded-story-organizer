@props(['navigation'])

<x-nav-link :href="route('projects.show', $navigation->project)" :active="$navigation->homeActive">
    {{ __('Dashboard') }}
</x-nav-link>

@if ($navigation->hasBook())
    <div class="flex items-center">
        <x-dropdown align="left" width="48">
            <x-slot name="trigger">
                <x-navigation.dropdown-trigger :active="$navigation->storyActive">{{ __('Story') }}</x-navigation.dropdown-trigger>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('books.story.home', $navigation->book)" :active="$navigation->storyHomeActive">
                    {{ __('Story') }}
                </x-dropdown-link>

                <x-dropdown-link :href="route('books.story.overview', $navigation->book)" :active="$navigation->storyOverviewActive">
                    {{ __('Overview') }}
                </x-dropdown-link>

                <x-dropdown-link :href="route('books.acts.index', $navigation->book)" :active="$navigation->actsActive">
                    {{ __('Acts') }}
                </x-dropdown-link>

                <x-dropdown-link :href="route('books.chapters.index', $navigation->book)" :active="$navigation->chaptersActive">
                    {{ __('Chapters') }}
                </x-dropdown-link>

                <x-dropdown-link :href="route('books.scenes.index', $navigation->book)" :active="$navigation->scenesActive">
                    {{ __('Scenes') }}
                </x-dropdown-link>
            </x-slot>
        </x-dropdown>
    </div>
@endif

<div class="flex items-center">
    <x-dropdown align="left" width="48">
        <x-slot name="trigger">
            <x-navigation.dropdown-trigger :active="$navigation->timelineActive">{{ __('Timeline') }}</x-navigation.dropdown-trigger>
        </x-slot>

        <x-slot name="content">
            <x-dropdown-link :href="route('projects.timeline.home', $navigation->project)" :active="$navigation->timelineHomeActive">
                {{ __('Timeline') }}
            </x-dropdown-link>

            <x-dropdown-link :href="route('projects.plotlines.index', $navigation->project)" :active="$navigation->plotlinesActive">
                {{ __('Plotlines') }}
            </x-dropdown-link>

            <x-dropdown-link :href="route('projects.events.index', $navigation->project)" :active="$navigation->eventsActive">
                {{ __('Events') }}
            </x-dropdown-link>
        </x-slot>
    </x-dropdown>
</div>

<div class="flex items-center">
    <x-dropdown align="left" width="48">
        <x-slot name="trigger">
            <x-navigation.dropdown-trigger :active="$navigation->codexActive">{{ __('Codex') }}</x-navigation.dropdown-trigger>
        </x-slot>

        <x-slot name="content">
            <x-dropdown-link :href="route('projects.codex.home', $navigation->project)" :active="$navigation->codexHomeActive">
                {{ __('Codex') }}
            </x-dropdown-link>

            @foreach (\App\Enums\CodexEntryType::cases() as $codexType)
                <x-dropdown-link
                    :href="route('projects.codex.index', [$navigation->project, $codexType->routeKey()])"
                    :active="$navigation->codexTypeIsActive($codexType)">
                    {{ __($codexType->pluralLabel()) }}
                </x-dropdown-link>
            @endforeach

            <x-dropdown-link :href="route('projects.codex-attributes.index', $navigation->project)" :active="$navigation->attributesActive">
                {{ __('Attributes') }}
            </x-dropdown-link>

            <x-dropdown-link :href="route('projects.tags.index', $navigation->project)" :active="$navigation->tagsActive">
                {{ __('Tags') }}
            </x-dropdown-link>
        </x-slot>
    </x-dropdown>
</div>

<div class="flex items-center">
    <x-dropdown align="left" width="48">
        <x-slot name="trigger">
            <x-navigation.dropdown-trigger :active="$navigation->toolsActive">{{ __('Tools') }}</x-navigation.dropdown-trigger>
        </x-slot>

        <x-slot name="content">
            <x-dropdown-link :href="route('projects.tools.home', $navigation->project)" :active="$navigation->toolsHomeActive">
                {{ __('Tools') }}
            </x-dropdown-link>

            <x-dropdown-link :href="route('projects.revisions.index', $navigation->project)" :active="$navigation->revisionsActive">
                {{ __('Revisions') }}
            </x-dropdown-link>

            <x-dropdown-link :href="route('projects.progress', $navigation->project)" :active="$navigation->progressActive">
                {{ __('Progress') }}
            </x-dropdown-link>
        </x-slot>
    </x-dropdown>
</div>

<x-nav-link
    :href="route('projects.search.index', $navigation->project)"
    :active="$navigation->searchActive"
    :aria-current="$navigation->searchActive ? 'page' : false">
    {{ __('Search') }}
</x-nav-link>
