@props(['navigation'])

{{-- Collapsed (mobile) primary menu: the same sections in the same order as
     navigation.project-menu, flattened — a heading per section instead of a
     dropdown. Same ProjectNavigation flags drive both. --}}
<x-responsive-nav-link :href="route('projects.show', $navigation->project)" :active="$navigation->homeActive">
    {{ __('Dashboard') }}
</x-responsive-nav-link>

<x-navigation.section-heading>{{ __('Story') }}</x-navigation.section-heading>

<x-responsive-nav-link :href="route('projects.story.index', $navigation->project)" :active="$navigation->storyOverviewActive">
    {{ __('Story Overview') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.acts.index', $navigation->project)" :active="$navigation->actsActive">
    {{ __('Acts') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.chapters.index', $navigation->project)" :active="$navigation->chaptersActive">
    {{ __('Chapters') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.scenes.index', $navigation->project)" :active="$navigation->scenesActive">
    {{ __('Scenes') }}
</x-responsive-nav-link>

<x-navigation.section-heading>{{ __('Timeline') }}</x-navigation.section-heading>

<x-responsive-nav-link :href="route('projects.plotlines.index', $navigation->project)" :active="$navigation->plotlinesActive">
    {{ __('Plotlines') }}
</x-responsive-nav-link>

<x-responsive-nav-link :href="route('projects.events.index', $navigation->project)" :active="$navigation->eventsActive">
    {{ __('Events') }}
</x-responsive-nav-link>

<x-navigation.section-heading>{{ __('Codex') }}</x-navigation.section-heading>

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

<x-responsive-nav-link :href="route('projects.revisions.index', $navigation->project)" :active="$navigation->toolsActive">
    {{ __('Revisions') }}
</x-responsive-nav-link>

<x-responsive-nav-link
    :href="route('projects.search.index', $navigation->project)"
    :active="$navigation->searchActive"
    :aria-current="$navigation->searchActive ? 'page' : false">
    {{ __('Search') }}
</x-responsive-nav-link>
