<x-app-layout>
    <x-page-heading>
        {{ __('New Note') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="note-create-form" method="POST" action="{{ route('projects.notes.store', $project) }}" class="space-y-6">
                @csrf

                @if ($linkTarget)
                    <input type="hidden" name="link" value="{{ $link }}">
                    <p class="text-sm text-content-muted" data-note-create-link>
                        {{ __('Linked to :type:', ['type' => __($linkType->label())]) }}
                        <a href="{{ route($linkType->showRoute(), $linkTarget) }}" class="text-link hover:text-link-hover">{{ $linkType->labelFor($linkTarget) }}</a>
                    </p>
                @endif
                <x-input-error :messages="$errors->get('link')" />

                <x-field name="title" :label="__('Title')">
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required autofocus />
                </x-field>

                <x-field name="note_category_id" :label="__('Category')">
                    <x-note-category-select id="note_category_id" name="note_category_id" class="mt-1 block w-full sm:text-sm" :tree="$categoryTree" :selected="old('note_category_id', $categoryId)" :empty-label="__('No category')" />
                </x-field>

                <x-field name="body" :label="__('Body')">
                    <x-wysiwyg id="body" name="body" :value="old('body')" :rows="12" />
                </x-field>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="note-create-form" :cancel="route('projects.notes.index', $project)">
                {{ __('Create Note') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
