<x-app-layout>
    <x-page-heading>{{ __('Timeline') }}</x-page-heading>

    <div class="grid gap-6 md:grid-cols-2">
        <x-recent-list
            :title="__('Recently edited plotlines')"
            :items="$recentPlotlines"
            :all-url="route('projects.plotlines.index', $project)"
            :all-label="__('View all plotlines')"
            :noun="__('plotlines')"
        />

        <x-recent-list
            :title="__('Recently edited events')"
            :items="$recentEvents"
            :all-url="route('projects.events.index', $project)"
            :all-label="__('View all events')"
            :noun="__('events')"
        />
    </div>
</x-app-layout>
