@props(['navigation'])

<x-responsive-nav-link :href="route('projects.show', $navigation->project)" :active="$navigation->homeActive">
    {{ __('Dashboard') }}
</x-responsive-nav-link>

@if ($navigation->hasBook())
    <x-navigation.section-heading>{{ __('Story') }}</x-navigation.section-heading>

    <x-responsive-nav-link :href="route('books.story.home', $navigation->book)" :active="$navigation->storyHomeActive">
        {{ __('Story') }}
    </x-responsive-nav-link>

    <x-responsive-nav-link :href="route('books.story.overview', $navigation->book)" :active="$navigation->storyOverviewActive">
        {{ __('Overview') }}
    </x-responsive-nav-link>

    <x-responsive-nav-link :href="route('books.acts.index', $navigation->book)" :active="$navigation->actsActive">
        {{ __('Acts') }}
    </x-responsive-nav-link>

    <x-responsive-nav-link :href="route('books.chapters.index', $navigation->book)" :active="$navigation->chaptersActive">
        {{ __('Chapters') }}
    </x-responsive-nav-link>

    <x-responsive-nav-link :href="route('books.scenes.index', $navigation->book)" :active="$navigation->scenesActive">
        {{ __('Scenes') }}
    </x-responsive-nav-link>
@endif

<x-navigation.section-heading>{{ __('Timeline') }}</x-navigation.section-heading>

<x-responsive-nav-link :href="route('projects.timeline.home', $navigation->project)" :active="$navigation->timelineHomeActive">
    {{ __('Timeline') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.plotlines.index', $navigation->project)" :active="$navigation->plotlinesActive">
    {{ __('Plotlines') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.events.index', $navigation->project)" :active="$navigation->eventsActive">
    {{ __('Events') }}
</x-responsive-nav-link>

<x-navigation.section-heading>{{ __('Codex') }}</x-navigation.section-heading>

<x-responsive-nav-link :href="route('projects.codex.home', $navigation->project)" :active="$navigation->codexHomeActive">
    {{ __('Codex') }}
</x-responsive-nav-link>

@foreach (\App\Enums\CodexEntryType::cases() as $codexType)
    <x-responsive-nav-link
        :href="route('projects.codex.index', [$navigation->project, $codexType->routeKey()])"
        :active="$navigation->codexTypeIsActive($codexType)">
        {{ __($codexType->pluralLabel()) }}
    </x-responsive-nav-link>
@endforeach

<x-responsive-nav-link :href="route('projects.codex-attributes.index', $navigation->project)" :active="$navigation->attributesActive">
    {{ __('Attributes') }}
</x-responsive-nav-link>

<x-navigation.section-heading>{{ __('Tools') }}</x-navigation.section-heading>

<x-responsive-nav-link :href="route('projects.tools.home', $navigation->project)" :active="$navigation->toolsHomeActive">
    {{ __('Tools') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.revisions.index', $navigation->project)" :active="$navigation->revisionsActive">
    {{ __('Revisions') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.progress', $navigation->project)" :active="$navigation->progressActive">
    {{ __('Progress') }}
</x-responsive-nav-link>

<x-responsive-nav-link
    :href="route('projects.search.index', $navigation->project)"
    :active="$navigation->searchActive"
    :aria-current="$navigation->searchActive ? 'page' : false">
    {{ __('Search') }}
</x-responsive-nav-link>
