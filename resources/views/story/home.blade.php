<x-app-layout>
    <x-page-heading>{{ __('Story') }}</x-page-heading>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <x-recent-list
            :title="__('Recently edited acts')"
            :items="$recentActs"
            :all-url="route('books.acts.index', $book)"
            :all-label="__('View all acts')"
            :noun="__('acts')"
        />

        <x-recent-list
            :title="__('Recently edited chapters')"
            :items="$recentChapters"
            :all-url="route('books.chapters.index', $book)"
            :all-label="__('View all chapters')"
            :noun="__('chapters')"
            show-covers
        />

        <x-recent-list
            :title="__('Recently edited scenes')"
            :items="$recentScenes"
            :all-url="route('books.scenes.index', $book)"
            :all-label="__('View all scenes')"
            :noun="__('scenes')"
        />
    </div>
</x-app-layout>
