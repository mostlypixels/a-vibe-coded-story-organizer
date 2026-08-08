<x-app-layout>
    <x-page-heading>{{ __('Codex') }}</x-page-heading>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach (\App\Enums\CodexEntryType::cases() as $codexType)
            @php($noun = __($codexType->pluralNoun()))

            <x-recent-list
                :title="__('Recently edited :items', ['items' => $noun])"
                :items="$recentEntries[$codexType->value]"
                :all-url="route('projects.codex.index', [$project, $codexType->routeKey()])"
                :all-label="__('View all :items', ['items' => $noun])"
                :noun="$noun"
                show-covers
            />
        @endforeach
    </div>
</x-app-layout>
