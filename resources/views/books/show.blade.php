<x-app-layout>
    <x-page-heading>
        {{ $book->displayName() }}
    </x-page-heading>

    <div class="mb-6 flex items-center justify-end">
        <x-word-count :count="$wordCount" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-9">
            <x-recent-list
                :title="__('Recent scenes')"
                :items="$recentScenes"
                :all-url="route('books.scenes.index', $book)"
                :all-label="__('View all scenes')"
                :noun="__('scenes')"
            />
        </div>

        <div class="lg:col-span-3">
            <x-card :title="__('Story')">
                <div class="space-y-2">
                    <x-button :href="route('books.story.overview', $book)" variant="secondary" class="w-full">
                        {{ __('Overview') }}
                    </x-button>
                    <x-button :href="route('books.acts.index', $book)" variant="secondary" class="w-full">
                        {{ __('Acts') }}
                    </x-button>
                    <x-button :href="route('books.chapters.index', $book)" variant="secondary" class="w-full">
                        {{ __('Chapters') }}
                    </x-button>
                    <x-button :href="route('books.scenes.index', $book)" variant="secondary" class="w-full">
                        {{ __('Scenes') }}
                    </x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
