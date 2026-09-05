<x-app-layout>
    <x-page-heading>
        {{ $project->name }} &mdash; {{ __('Tags') }}
    </x-page-heading>

    <div class="space-y-6">
        <x-card :title="__('Add a tag')">
            <form method="POST" action="{{ route('projects.tags.store', $project) }}" class="flex items-start gap-2">
                @csrf
                <div class="flex-1">
                    <x-input-label for="name" :value="__('Tag name')" class="sr-only" />
                    <x-text-input id="name" name="name" type="text" class="w-full" :value="old('name')"
                        placeholder="{{ __('e.g. Protagonist') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <x-button variant="primary" type="submit">{{ __('Add tag') }}</x-button>
            </form>
        </x-card>

        <x-table>
            <x-slot:head>
                <x-table-heading>{{ __('Name') }}</x-table-heading>
                <x-table-heading>{{ __('Entries') }}</x-table-heading>
                <x-table-heading />
            </x-slot:head>

            @forelse ($tags as $tag)
                <x-table-row :striped="$loop->even">
                    <x-table-cell>
                        <form method="POST" action="{{ route('tags.update', $tag) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <x-input-label :for="'name-'.$tag->id" :value="__('Rename tag')" class="sr-only" />
                            <x-text-input :id="'name-'.$tag->id" name="name" type="text" class="text-sm" :value="$tag->name" required />
                            <x-icon-save-button />
                        </form>
                    </x-table-cell>
                    <x-table-cell muted>
                        {{ $tag->entries_count }}
                    </x-table-cell>
                    <x-table-cell align="right" nowrap>
                        <div class="flex items-center justify-end">
                            <x-icon-delete-button
                                :action="route('tags.destroy', $tag)"
                                :confirm="__('Delete this tag? It will be removed from :count entries.', ['count' => $tag->entries_count])" />
                        </div>
                    </x-table-cell>
                </x-table-row>
            @empty
                <x-table-empty :colspan="3">{{ __('No tags yet. Add one above, or tag an entry on its page.') }}</x-table-empty>
            @endforelse
        </x-table>
    </div>
</x-app-layout>
