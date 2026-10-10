<x-app-layout>
    <x-page-heading>
        {{ __('Edit Note') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="note-edit-form" method="POST" action="{{ route('notes.update', $note) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <x-field name="title" :label="__('Title')">
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $note->title)" required autofocus />
                </x-field>

                <x-field name="note_category_id" :label="__('Category')">
                    <x-note-category-select id="note_category_id" name="note_category_id" class="mt-1 block w-full sm:text-sm" :tree="$categoryTree" :selected="old('note_category_id', $note->note_category_id)" :empty-label="__('No category')" />
                </x-field>

                <div>
                    <x-autosave-field entity="note" :model="$note" field="body" :label="__('Body')" />
                </div>
            </form>
        </x-card>

        @include('notes.partials.links-card')

        <x-slot:sidebar>
            <x-edit-actions
                form="note-edit-form"
                :history-model="$note"
                :delete-action="route('notes.destroy', $note)"
                :delete-confirm="__('Are you sure you want to delete this note?')"
            >
                {{ __('Delete Note') }}
            </x-edit-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
