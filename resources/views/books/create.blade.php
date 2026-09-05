<x-app-layout>
    <x-page-heading>
        {{ __('New Book') }}
    </x-page-heading>

    <x-edit-layout>
        <x-card>
            <form id="book-create-form" method="POST" action="{{ route('projects.books.store', $project) }}" class="space-y-6">
                @csrf

                <x-field name="name" :label="__('Name')">
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" :required="$nameRequired" autofocus />
                </x-field>

                <x-field name="description" :label="__('Description')">
                    <x-wysiwyg id="description" name="description" :value="old('description')" :rows="4" />
                </x-field>
            </form>
        </x-card>

        <x-slot:sidebar>
            <x-create-actions form="book-create-form" :cancel="route('projects.books.index', $project)">
                {{ __('Create Book') }}
            </x-create-actions>
        </x-slot:sidebar>
    </x-edit-layout>
</x-app-layout>
