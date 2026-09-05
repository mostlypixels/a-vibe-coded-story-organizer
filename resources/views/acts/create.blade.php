<x-app-layout>
    <x-page-heading>
        {{ __('New Act') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="act-create-form" method="POST" action="{{ route('books.acts.store', $book) }}" class="space-y-6">
                @csrf

                <x-field name="name" :label="__('Title')" :hint="__('The act number is assigned automatically and can be changed later by reordering.')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="{{ __('e.g. The Curse of Pressine') }}" required autofocus />
                </x-field>

                <x-field name="description" :label="__('Description')">
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                </x-field>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="act-create-form" :cancel="route('books.acts.index', $book)">
                {{ __('Create Act') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
