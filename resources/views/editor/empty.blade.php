<x-app-layout>
    <x-page-heading>{{ __('Write') }}</x-page-heading>

    <x-card>
        <div class="flex flex-col items-center gap-3 py-6 text-center" data-editor-empty>
            <x-tabler-writing class="h-10 w-10 text-content-subtle" aria-hidden="true" />
            <p class="font-medium text-content-muted">{{ __('No scenes yet. Create a scene to start writing.') }}</p>
            @if ($book)
                <x-button variant="primary" :href="route('books.scenes.create', $book)">{{ __('Create a scene') }}</x-button>
            @endif
        </div>
    </x-card>
</x-app-layout>
